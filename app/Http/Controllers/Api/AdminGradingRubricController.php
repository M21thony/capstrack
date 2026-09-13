<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradingRubric;
use App\Models\RubricCriterion;
use App\Services\OllamaSSHClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminGradingRubricController extends Controller
{
    private const TYPE_MAP = [
        'pre_proposal' => 'Pre-Proposal Defense',
        'title_defense' => 'Title Defense',
        'pre_oral_defense' => 'Pre Oral Defense',
        'final_oral_defense' => 'Final Oral Defense',
    ];

    private const LEVEL_KEYS = ['excellent', 'good', 'fair', 'poor'];

    private const LEVEL_LABELS = ['Excellent', 'Good', 'Fair', 'Poor'];

    private const TYPE_MAP_REVERSE = [
        'Pre-Proposal Defense' => 'pre_proposal',
        'Title Defense' => 'title_defense',
        'Pre Oral Defense' => 'pre_oral_defense',
        'Final Oral Defense' => 'final_oral_defense',
    ];

    // Transcribed verbatim from the school's actual Pre-Proposal Defense
    // Rubric (Manuscript / Topic Quality and Proposal Merit / Proposal
    // Content and Clarity), points included. Where the source document
    // repeats the exact same sentence across all four performance levels
    // for a row (differing only by point value - true of every row under
    // Topic Quality and Proposal Merit, and Proposal Content and Clarity),
    // that repetition is preserved rather than invented, since the goal is
    // reproducing the real document, not improving on it.
    //
    // The source scores each row by the panel picking ONE of its four
    // point values - the "excellent" value is that row's ceiling
    // (max_points), the other three are lower alternative scores for the
    // same criterion, not additional points to add on top.
    //
    // NOTE ON "Study Design Methodology": the source document's Poor value
    // for this row is (8.5pts), which is higher than its own Fair value
    // (3pts) - almost certainly a typo in the original, since "Poor"
    // scoring more than "Fair" defeats the point of the rubric. Using 1.5
    // here (continuing that row's roughly-halving pattern: 10, 5, 3, 1.5)
    // as a placeholder pending confirmation of the intended value.
    private const PRE_PROPOSAL_CRITERIA = [
        [
            'criterion_name' => 'Team Readiness and Presentability',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses whether the team appeared ready and presentable for the defense.',
            'levels' => [
                'excellent' => ['desc' => 'All the team members appeared to be ready and presentable.', 'score' => 5],
                'good' => ['desc' => 'All the team members appeared to be ready but not all are presentable.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the team members appeared to be ready and presentable.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the team members appeared to be ready and not presentable.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Slide Usage',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how intelligently and strategically the presentation slides were used.',
            'levels' => [
                'excellent' => ['desc' => 'All the presentation slides were intelligently and strategically used.', 'score' => 5],
                'good' => ['desc' => 'At least 75% of the presentation slides were intelligently and strategically used.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the presentation slides were intelligently and strategically used.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the presentation slides were intelligently and strategically used.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Oral Delivery',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses clarity, confidence, and command of language during the defense.',
            'levels' => [
                'excellent' => ['desc' => 'Delivery is clear, confident, and articulate; excellent command of language.', 'score' => 5],
                'good' => ['desc' => 'Delivery is clear with minor lapses.', 'score' => 3.75],
                'fair' => ['desc' => 'Delivery is understandable but lacks confidence or clarity.', 'score' => 2.5],
                'poor' => ['desc' => 'Delivery is unclear and difficult to follow.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Panel Q&A Handling',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how confidently and satisfactorily the team answered panel questions and queries.',
            'levels' => [
                'excellent' => ['desc' => 'All panel questions and queries were confidently and satisfactorily answered.', 'score' => 5],
                'good' => ['desc' => 'At least 75% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Project Achievability',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses whether the project is achievable within time, skills, and resources.',
            'levels' => [
                'excellent' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 6],
                'good' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 4],
                'fair' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 2.5],
                'poor' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Topic Engagement and Value',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses the academic and practical value of the chosen topic.',
            'levels' => [
                'excellent' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 6],
                'good' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 4],
                'fair' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 2.5],
                'poor' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Originality and Innovation',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses the originality or innovative approach demonstrated by the proposal.',
            'levels' => [
                'excellent' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 6],
                'good' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 4],
                'fair' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 2.5],
                'poor' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Ethical and Privacy Considerations',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses whether ethical issues and data privacy are identified and properly addressed.',
            'levels' => [
                'excellent' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 6],
                'good' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 4],
                'fair' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 2.5],
                'poor' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Alignment with Research Agenda',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses alignment with the research agenda, SDGs, and stakeholder needs.',
            'levels' => [
                'excellent' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 6],
                'good' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 4],
                'fair' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 2.5],
                'poor' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Problem Clarity and Justification',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the problem is clear, specific, well-justified, and evidence-based.',
            'levels' => [
                'excellent' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 20],
                'good' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 12.5],
                'fair' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 8.5],
                'poor' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 4.25],
            ],
        ],
        [
            'criterion_name' => 'Solution Scope and Definition',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the solution addresses the problem with well-defined scope and limitations.',
            'levels' => [
                'excellent' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 20],
                'good' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 12.5],
                'fair' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 8.5],
                'poor' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 4.25],
            ],
        ],
        [
            'criterion_name' => 'Study Design Methodology',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the study design methodology (SDM) is appropriate and clearly justified.',
            'levels' => [
                'excellent' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 10],
                'good' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 5],
                'fair' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 3],
                'poor' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 1.5],
            ],
        ],
    ];

    // Transcribed verbatim from the school's actual Title Defense Grading
    // Rubric (BSIT). Unlike PRE_PROPOSAL_CRITERIA, the source only rates
    // each row on THREE tiers (not four) - there's no "good" level in the
    // real document, just a top/middle/bottom point value per row with no
    // level names printed at all. 'excellent'/'fair'/'poor' are used as the
    // three keys here (reusing 3 of the same names PRE_PROPOSAL_CRITERIA
    // uses) purely as display labels; the source document itself doesn't
    // name these tiers.
    //
    // Every category's "excellent" column sums exactly to that category's
    // printed ceiling (e.g. Chapter 1: 3+3+5+2+3+3+1 = 20), and all 7
    // category ceilings sum to exactly 100 - both confirm this was
    // transcribed correctly, not just each row in isolation.
    //
    // NOTE: Chapter 3's own printed band headers (20 / 12 / 5) don't
    // exactly match the sum of its 7 rows' middle/bottom columns (11.5 and
    // 4.5, not 12 and 5) - almost certainly a rounding slip in the source
    // document, same as the pre-existing SDM note above. The per-row
    // numbers below are transcribed exactly as printed rather than
    // adjusted to force the chapter header to match.
    private const TITLE_DEFENSE_CRITERIA = [
        // ── Chapter 1 (ceiling 20) ──────────────────────────────────────
        [
            'criterion_name' => 'Project Context',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether the discussion provides a clear overview of the background of the problem being addressed.',
            'levels' => [
                'excellent' => ['desc' => 'The discussion is completely intact and provides a clear overview of the background of the problem that needs to be addressed.', 'score' => 3],
                'fair' => ['desc' => 'The discussion is slightly intact and provides a slightly clear overview of the background of the problem that needs to be addressed.', 'score' => 2],
                'poor' => ['desc' => 'The discussion is not intact and does not provide a clear overview of the background of the problem that needs to be addressed.', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'Purpose and Description of the Project',
            'category' => 'Chapter 1',
            'description' => "Assesses the discussion of the project's main function, advantage, and uniqueness.",
            'levels' => [
                'excellent' => ['desc' => 'Clearly discusses the main function of the project (on the first paragraph), the advantage/good of the project (on the second paragraph), and uniqueness of the project (on the third paragraph).', 'score' => 3],
                'fair' => ['desc' => 'Slightly clear discussion of the main function of the project (on the first paragraph), the advantage/good of the project (on the second paragraph), and uniqueness of the project (on the third paragraph).', 'score' => 2],
                'poor' => ['desc' => 'There is no clear discussion of the main function of the project (on the first paragraph), the advantage/good of the project (on the second paragraph), and uniqueness of the project (on the third paragraph).', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'Objectives of the Project',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether the objectives are SMART (Specific, Measurable, Achievable, Relevant, Time-bound).',
            'levels' => [
                'excellent' => ['desc' => 'The objectives are 100% SMART.', 'score' => 5],
                'fair' => ['desc' => 'The objectives are at least 50% SMART.', 'score' => 2.5],
                'poor' => ['desc' => 'The objectives are 0% SMART.', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'Significance of the Study',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether the significance is presented across personal, community, and computing-field perspectives.',
            'levels' => [
                'excellent' => ['desc' => 'Clearly presents the significance of the study/project based on three perspectives: personal, community and computing field.', 'score' => 2],
                'fair' => ['desc' => 'Slightly clear presentation of the significance of the study/project based on three perspectives: personal, community and computing field.', 'score' => 1],
                'poor' => ['desc' => 'No clear presentation of the significance of the study/project based on three perspectives: personal, community and computing field.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Scope and Limitation of the Project',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether coverage and constraints are clearly discussed, answering the What, Who, Where, When, Why, How.',
            'levels' => [
                'excellent' => ['desc' => 'Clearly discusses the coverage and constraints based on the nature of the project and the allotted timeframe; answers the What, Who, Where, When, Why, How.', 'score' => 3],
                'fair' => ['desc' => 'Slightly clear discussion of the coverage and constraints based on the nature of the project and the allotted timeframe; answers the What, Who, Where, When, Why, How.', 'score' => 1.5],
                'poor' => ['desc' => 'There is no clear discussion of the coverage and constraints based on the nature of the project and the allotted timeframe; answers the What, Who, Where, When, Why, How.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Project Dictionary',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether technical keywords are relevant and defined both operationally and conceptually, with proper citations.',
            'levels' => [
                'excellent' => ['desc' => 'All the technical keywords are relevant to the project and are defined both operationally and conceptually; operational definitions are cited properly.', 'score' => 3],
                'fair' => ['desc' => 'Not all the technical keywords are relevant to the project and are defined both operationally and conceptually; operational definitions are cited properly.', 'score' => 1.5],
                'poor' => ['desc' => 'All the technical keywords are irrelevant to the project and are not defined both operationally and conceptually; operational definitions are not cited properly.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Notes Page',
            'category' => 'Chapter 1',
            'description' => 'Assesses whether the notes page follows the ACM reference format.',
            'levels' => [
                'excellent' => ['desc' => '100% follows the ACM reference format.', 'score' => 1],
                'fair' => ['desc' => 'At least 50% follows the ACM reference format.', 'score' => 0.5],
                'poor' => ['desc' => 'The entire notes page does not follow the ACM reference format.', 'score' => 0],
            ],
        ],

        // ── Chapter 2 (ceiling 15) ──────────────────────────────────────
        [
            'criterion_name' => 'Review of Related Literatures and Systems',
            'category' => 'Chapter 2',
            'description' => 'Assesses the recency, relevance, and proper ACM-format citation of related literature, organized topically.',
            'levels' => [
                'excellent' => ['desc' => '100% of the RLs are recent and relevant; 100% of the RLs are appropriately cited and noted, in ACM format; Discussion of the RLs is in topical format; Each topic has at least 5 RLs cited.', 'score' => 5],
                'fair' => ['desc' => 'At least 50% of the RLs are recent and relevant; At least 50% of the RLs are appropriately cited and noted, in ACM format; Discussion of the RLs is in topical format; Each topic has at least 3 RLs cited.', 'score' => 3],
                'poor' => ['desc' => 'Less than 50% of the RLs are recent and relevant; Less than 50% of the RLs are appropriately cited and noted, in ACM format; Discussion of the RLs is not in topical format; Each topic has less than 3 RLs cited.', 'score' => 1.5],
            ],
        ],
        [
            'criterion_name' => 'Synthesis of the State of the Art',
            'category' => 'Chapter 2',
            'description' => 'Assesses the discussion of similarities and differences between the present study and related literature/systems.',
            'levels' => [
                'excellent' => ['desc' => 'There is a comprehensive discussion of the similarities and differences between the present study against the related literature and systems mentioned.', 'score' => 5],
                'fair' => ['desc' => 'There is a less comprehensive discussion of the similarities and differences between the present study against the related literature and systems mentioned.', 'score' => 3],
                'poor' => ['desc' => 'There is no comprehensive discussion of the similarities and differences between the present study against the related literature and systems mentioned.', 'score' => 1.5],
            ],
        ],
        [
            'criterion_name' => 'Gap Bridged by the Study',
            'category' => 'Chapter 2',
            'description' => 'Assesses the discussion on bridging the gap between related capstone projects and the present project.',
            'levels' => [
                'excellent' => ['desc' => 'There is a comprehensive discussion on bridging the gap between the related capstone projects and the present project.', 'score' => 3],
                'fair' => ['desc' => 'There is a less comprehensive discussion on bridging the gap between the related capstone projects and the present project.', 'score' => 1.5],
                'poor' => ['desc' => 'There is no comprehensive discussion on bridging the gap between the related capstone projects and the present project.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Notes Page',
            'category' => 'Chapter 2',
            'description' => 'Assesses whether at least twenty sources are listed in ACM format.',
            'levels' => [
                'excellent' => ['desc' => 'There are more than twenty (20) sources listed in ACM format.', 'score' => 2],
                'fair' => ['desc' => 'There are at least twenty (20) sources listed in ACM format.', 'score' => 1],
                'poor' => ['desc' => 'There are fewer than twenty (20) sources listed in ACM format.', 'score' => 0.5],
            ],
        ],

        // ── Chapter 3 (ceiling 20) ──────────────────────────────────────
        [
            'criterion_name' => 'Research Design and Methods',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the research design and methods are clearly presented and appropriate for the system.',
            'levels' => [
                'excellent' => ['desc' => 'The research design and methods are clearly presented and appropriate for the development and evaluation of the proposed system.', 'score' => 4],
                'fair' => ['desc' => 'The research design and methods are slightly presented and somewhat appropriate for the development and evaluation of the proposed system.', 'score' => 2.5],
                'poor' => ['desc' => 'The research design and methods are not clearly presented and are not appropriate for the development and evaluation of the proposed system.', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'Data Sources and Respondents',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether data sources, sampling technique, and respondents are clearly identified and appropriate.',
            'levels' => [
                'excellent' => ['desc' => 'The data sources, sampling technique, and respondents are clearly identified and appropriate for the development and evaluation of the proposed system.', 'score' => 4],
                'fair' => ['desc' => 'The data sources, sampling technique, and respondents are slightly identified and somewhat appropriate for the development and evaluation of the proposed system.', 'score' => 2.5],
                'poor' => ['desc' => 'The data sources, sampling technique, and respondents are not clearly identified and are not appropriate for the development and evaluation of the proposed system.', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'Research Instruments, Data Gathering and Analysis Procedure',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the instruments and procedures for gathering/analyzing data are clearly presented and aligned with the study objectives.',
            'levels' => [
                'excellent' => ['desc' => 'The instruments used for data collection and the procedures for gathering and analyzing data are clearly presented and aligned with the objectives of the study.', 'score' => 4],
                'fair' => ['desc' => 'The instruments used for data collection and the procedures for gathering and analyzing data are slightly presented and somewhat aligned with the objectives of the study.', 'score' => 2.5],
                'poor' => ['desc' => 'The instruments used for data collection and the procedures for gathering and analyzing data are not clearly presented and are not aligned with the objectives of the study.', 'score' => 1],
            ],
        ],
        [
            'criterion_name' => 'System Development Approach',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the approach followed in developing the proposed system is clearly discussed and appropriate.',
            'levels' => [
                'excellent' => ['desc' => 'The approach followed in developing the proposed system is clearly discussed and appropriate to the nature of the project.', 'score' => 2],
                'fair' => ['desc' => 'The approach followed in developing the proposed system is slightly discussed and somewhat appropriate to the nature of the project.', 'score' => 1],
                'poor' => ['desc' => 'The approach followed in developing the proposed system is not clearly discussed and is not appropriate to the nature of the project.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'System Architecture and Technical Requirements',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the system structure and technical requirements are clearly described and appropriate to the project.',
            'levels' => [
                'excellent' => ['desc' => 'The overall structure of the system and the technical requirements needed for development and deployment are clearly described and appropriate to the project.', 'score' => 2],
                'fair' => ['desc' => 'The overall structure of the system and the technical requirements needed for development and deployment are slightly described and somewhat appropriate to the project.', 'score' => 1],
                'poor' => ['desc' => 'The overall structure of the system and the technical requirements needed for development and deployment are not clearly described and are not appropriate to the project.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Testing and Evaluation Plan',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the testing and evaluation plan is clearly presented and appropriate to the study objectives.',
            'levels' => [
                'excellent' => ['desc' => 'The testing and evaluation plan for the proposed system is clearly presented and appropriate to the objectives of the study.', 'score' => 3],
                'fair' => ['desc' => 'The testing and evaluation plan for the proposed system is slightly presented and somewhat appropriate to the objectives of the study.', 'score' => 1.5],
                'poor' => ['desc' => 'The testing and evaluation plan for the proposed system is not clearly presented and is not appropriate to the objectives of the study.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Notes Page',
            'category' => 'Chapter 3',
            'description' => 'Assesses whether the notes page follows the ACM reference format.',
            'levels' => [
                'excellent' => ['desc' => '100% follows the ACM reference format.', 'score' => 1],
                'fair' => ['desc' => 'At least 50% follows the ACM reference format.', 'score' => 0.5],
                'poor' => ['desc' => 'The entire notes page does not follow the ACM reference format.', 'score' => 0],
            ],
        ],

        // ── Grammar and Spelling (ceiling 10) ───────────────────────────
        [
            'criterion_name' => 'Grammar and Spelling',
            'category' => 'Grammar and Spelling',
            'description' => 'Assesses the presence of grammatical/typographical errors and variety of sentence structure.',
            'levels' => [
                'excellent' => ['desc' => 'Grammatical and typographical errors are absent; writer uses a variety of sentence structures to improve the flow of discussion.', 'score' => 10],
                'fair' => ['desc' => 'Moderate grammatical and typographical errors are present, the writer uses a variety of sentence structures to improve the flow of discussion.', 'score' => 6],
                'poor' => ['desc' => 'Too many grammatical and typographical errors are present, and the writer does not use a variety of sentence structures to improve the flow of discussion.', 'score' => 3],
            ],
        ],

        // ── Oral Examination/Presentation (ceiling 20) ──────────────────
        [
            'criterion_name' => 'Team Readiness and Presentability',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses whether the team appeared ready and presentable for the defense.',
            'levels' => [
                'excellent' => ['desc' => 'All the team members appeared to be ready and presentable.', 'score' => 5],
                'fair' => ['desc' => 'Only 50% of the team members appeared to be ready and presentable.', 'score' => 2.5],
                'poor' => ['desc' => 'All of the team members appeared to be not ready and not presentable.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Slide Usage',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how intelligently and strategically the presentation slides were used.',
            'levels' => [
                'excellent' => ['desc' => 'All the presentation slides were intelligently and strategically used.', 'score' => 5],
                'fair' => ['desc' => 'Only 50% of the presentation slides were intelligently and strategically used.', 'score' => 2.5],
                'poor' => ['desc' => 'All of the presentation slides were not intelligently and strategically used.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Delivery/Command of the English Language',
            'category' => 'Oral Examination/Presentation',
            'description' => "Assesses the team's delivery and command of the English language.",
            'levels' => [
                'excellent' => ['desc' => 'Delivery / Command of the English Language was excellent.', 'score' => 5],
                'fair' => ['desc' => 'Delivery / Command of the English Language were satisfactory.', 'score' => 2.5],
                'poor' => ['desc' => 'Delivery / Command of the English Language were poor.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Panel Q&A Handling',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how confidently and satisfactorily the team answered panel questions and queries.',
            'levels' => [
                'excellent' => ['desc' => 'All panel questions and queries were confidently and satisfactorily answered.', 'score' => 5],
                'fair' => ['desc' => 'Only 50% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 2.5],
                'poor' => ['desc' => 'All of the panel questions and queries were not confidently and satisfactorily answered.', 'score' => 1.25],
            ],
        ],

        // ── Research Instrument (ceiling 10) ────────────────────────────
        [
            'criterion_name' => 'Validity, Reliability, and Succinctness',
            'category' => 'Research Instrument',
            'description' => 'Assesses whether the presented research instrument is valid, reliable, and succinct.',
            'levels' => [
                'excellent' => ['desc' => 'The presented research instrument is valid, reliable, and succinct.', 'score' => 5],
                'fair' => ['desc' => 'The presented research instrument is partially valid, reliable, and succinct.', 'score' => 3],
                'poor' => ['desc' => 'The presented research instrument is totally not valid, nor reliable nor succinct.', 'score' => 1.5],
            ],
        ],
        [
            'criterion_name' => 'Consistency with Objectives',
            'category' => 'Research Instrument',
            'description' => "Assesses whether the research instrument is consistent with the project's objectives.",
            'levels' => [
                'excellent' => ['desc' => 'Consistent with the objectives of the project.', 'score' => 5],
                'fair' => ['desc' => 'Partially consistent with the objectives of the project.', 'score' => 3],
                'poor' => ['desc' => 'Totally not consistent with the objectives of the project.', 'score' => 1.5],
            ],
        ],

        // ── Timeliness (ceiling 5) ───────────────────────────────────────
        [
            'criterion_name' => 'On-Time Defense',
            'category' => 'Timeliness',
            'description' => 'Assesses whether the title defense was held on the scheduled date and the team was on time.',
            'levels' => [
                'excellent' => ['desc' => 'The team was able to have the title defense on scheduled date; capstone team are on time.', 'score' => 2.5],
                'fair' => ['desc' => 'The team was able to have the title defense on the scheduled date, but the capstone team arrived late.', 'score' => 1.5],
                'poor' => ['desc' => 'The team was not able to have the title defense on the scheduled date.', 'score' => 0.5],
            ],
        ],
        [
            'criterion_name' => 'Manuscript Submission Timing',
            'category' => 'Timeliness',
            'description' => 'Assesses how far in advance of the title defense the manuscript copy was provided.',
            'levels' => [
                'excellent' => ['desc' => 'The team was able to provide a copy of the manuscript 2 days before the title defense schedule.', 'score' => 2.5],
                'fair' => ['desc' => 'The team was able to provide a copy of the manuscript a day before the title defense schedule.', 'score' => 1.5],
                'poor' => ['desc' => 'The team was able to provide a copy of the manuscript during title defense schedule.', 'score' => 0.5],
            ],
        ],
    ];

    public function __construct(private OllamaSSHClient $ollama) {}

    // POST /api/admin/grading-rubrics/drafts — drafts a rubric (name,
    // description, criteria with per-level narrative descriptions) via the
    // same Ollama model used by the duplication checker. Scores are NOT
    // taken from the model; they're computed deterministically afterward
    // so the draft always satisfies the "all score-levels sum to exactly
    // 100" rule the rest of this controller enforces. Returns a draft for
    // the admin to review/edit in the modal - nothing is saved here.
    public function aiGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'topic' => ['nullable', 'string', 'max:255'],
            'criteria_count' => ['nullable', 'integer', 'min:3', 'max:15'],
        ]);

        $typeLabel = self::TYPE_MAP[$validated['rubric_type']];
        $isPreProposal = $validated['rubric_type'] === 'pre_proposal';
        $isTitleDefense = $validated['rubric_type'] === 'title_defense';
        // Pre-Proposal Defense and Title Defense both have a real reference
        // rubric, so there's nothing to type and no count to pick - the AI
        // is asked to reproduce all of its actual criteria, not invent a
        // subset.
        $criteriaCount = $isPreProposal
            ? count(self::PRE_PROPOSAL_CRITERIA)
            : ($isTitleDefense ? count(self::TITLE_DEFENSE_CRITERIA) : ($validated['criteria_count'] ?? 5));
        $topic = trim($validated['topic'] ?? '');
        $fixedCategories = $this->defaultCategoriesFor($validated['rubric_type']);

        // Title Defense's reference rubric is used completely as-is -
        // unlike the other types, there's no wording left for an AI to
        // usefully contribute, since TITLE_DEFENSE_CRITERIA already holds
        // the real, complete, verbatim text for every one of its 27
        // criteria. Routing this through an actual AI reproduction call was
        // tried and directly tested against the real capstrack-duplication
        // model: asked to echo back all 27, it returned a clean, validly-
        // parsing, but SILENTLY INCOMPLETE response - only 11 of 27
        // criteria, dropping all of Chapter 2 and Chapter 3 entirely (a
        // "lost in the middle" failure common to small models on long
        // structured-reproduction tasks), for a grand total of 51 instead
        // of 100. Since the real data is already fully known, the AI call
        // is skipped entirely for this type - instant and immune to that
        // failure mode, rather than trying to prompt-engineer around it.
        if ($isTitleDefense) {
            $criteria = $this->scoreTitleDefenseCriteria($this->titleDefenseCriteriaWordingOnly());

            return response()->json([
                'success' => true,
                'message' => 'Draft rubric loaded from the real Title Defense rubric. Review and edit before saving.',
                'data' => [
                    'rubric_name' => 'Title Defense Rubric',
                    'description' => "Evaluates a capstone team's title defense across Chapters 1-3, grammar and spelling, oral examination/presentation, research instrument, and timeliness.",
                    'categories' => $this->summarizeCategories($criteria),
                    'criteria' => $criteria,
                ],
            ]);
        }

        $prompt = $isPreProposal
            ? $this->buildPreProposalReproductionPrompt()
            : $this->buildRubricPrompt($typeLabel, $criteriaCount, $topic, $fixedCategories);

        // The pre-proposal reproduction prompt embeds all 12 real criteria
        // and expects a similarly-sized echo back - needs more headroom
        // than the shorter free-generation prompts for the other types.
        // The free-generation budget scales with criteria_count (now up to
        // 15) so a large request doesn't get truncated mid-JSON the way a
        // fixed 1800-token cap would.
        $maxTokens = $isPreProposal ? 3000 : max(1800, $criteriaCount * 250);

        // PHP's default max_execution_time (120s on this server) doesn't
        // leave room for a cold model load (~50s alone) plus warmup() and
        // generate() as two sequential calls. 240s matches the same budget
        // given to the (longer) duplication-check chain.
        set_time_limit(240);

        try {
            // Loading the model from cold can take ~50s on its own (seen
            // directly in the Ollama server log). Warming up first spends
            // that cost on a trivial 1-token prompt instead of eating into
            // the real generate() call's own timeout budget - warmup()
            // swallows its own failures internally, so a slow/failed
            // warmup here doesn't abort the request; it just means the
            // model wasn't pre-loaded and the real call pays the cost.
            $this->ollama->warmup();

            $raw = $this->ollama->generate($prompt, $maxTokens);
        } catch (\Throwable $e) {
            // \Throwable, not \Exception - a slow/dead SSH tunnel can hit
            // PHP's max_execution_time and fatal with a Symfony FatalError
            // (extends \Error, not \Exception), which `catch (\Exception)`
            // doesn't catch - it would escape straight to Laravel's HTML
            // error page instead of a clean JSON response.
            Log::error('Rubric AI generation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reach the AI model. Please try again.',
            ], 502);
        }

        $parsed = $this->extractRubricJson($raw);

        if (! $parsed || empty($parsed['criteria']) || ! is_array($parsed['criteria'])) {
            Log::error('Rubric AI generation returned unparseable output', ['raw' => $raw]);

            return response()->json([
                'success' => false,
                'message' => 'The AI response could not be parsed into a rubric. Please try again.',
            ], 502);
        }

        if ($isPreProposal) {
            $criteria = $this->scorePreProposalCriteria(array_slice($parsed['criteria'], 0, $criteriaCount));
        } else {
            // $isTitleDefense never reaches here - it returns early above,
            // before any AI call is made.
            $categories = $fixedCategories ?? $this->extractCategories($parsed['categories'] ?? null);
            $criteria = $this->scoreCriteriaWithCategories(array_slice($parsed['criteria'], 0, $criteriaCount), $categories);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft rubric generated. Review and edit before saving.',
            'data' => [
                'rubric_name' => trim((string) ($parsed['rubric_name'] ?? '')) ?: "{$typeLabel} Rubric",
                'description' => trim((string) ($parsed['description'] ?? '')),
                'categories' => $this->summarizeCategories($criteria),
                'criteria' => $criteria,
            ],
        ]);
    }

    // The real category breakdown from the school's actual Pre-Proposal
    // Defense Rubric (Manuscript / Topic Quality and Proposal Merit /
    // Proposal Content and Clarity, weighted 20/30/50). Used verbatim for
    // that rubric type so the AI draft's structure - not just its wording -
    // matches the real thing; other types let the model propose its own
    // categories since no reference structure exists for them.
    private function defaultCategoriesFor(string $rubricTypeSlug): ?array
    {
        if ($rubricTypeSlug === 'pre_proposal') {
            return [
                ['name' => 'Oral Examination/Presentation', 'weight' => 20],
                ['name' => 'Topic Quality and Proposal Merit', 'weight' => 30],
                ['name' => 'Proposal Content and Clarity', 'weight' => 50],
            ];
        }

        if ($rubricTypeSlug === 'title_defense') {
            return [
                ['name' => 'Chapter 1', 'weight' => 20],
                ['name' => 'Chapter 2', 'weight' => 15],
                ['name' => 'Chapter 3', 'weight' => 20],
                ['name' => 'Grammar and Spelling', 'weight' => 10],
                ['name' => 'Oral Examination/Presentation', 'weight' => 20],
                ['name' => 'Research Instrument', 'weight' => 10],
                ['name' => 'Timeliness', 'weight' => 5],
            ];
        }

        return null;
    }

    // Pulls real criteria straight from PRE_PROPOSAL_CRITERIA to use as
    // few-shot style examples for the free-generation prompt (the other 3
    // rubric types, which have no reference document of their own). Only
    // the 4 Manuscript rows have genuinely differentiated per-level wording
    // - the rest intentionally repeat one sentence across all 4 levels
    // (see PRE_PROPOSAL_CRITERIA's note), which would teach the model the
    // wrong lesson about *varying* wording per level. One example produced
    // vague hedging like "while clear, there are minor areas where the
    // presentation could be more concise" - multiple real anchors give it
    // less room to drift back into generic LLM phrasing.
    private function realStyleExamples(): array
    {
        return array_map(fn ($c) => [
            'criterion_name' => $c['criterion_name'],
            'description' => $c['description'],
            'levels' => array_map(fn ($lv) => $lv['desc'], $c['levels']),
        ], array_slice(self::PRE_PROPOSAL_CRITERIA, 0, 4));
    }

    // Rather than asking the model to invent a Pre-Proposal Defense rubric,
    // it's handed the real one (PRE_PROPOSAL_CRITERIA) as the answer and
    // asked to reproduce it verbatim in the required JSON shape - a much
    // easier, more reliable task than free generation, while still going
    // through the same AI call as every other rubric type. Point values are
    // deliberately left out of what's sent to the model (see
    // scorePreProposalCriteria()) - wording is the model's job, the real
    // numbers are looked up afterward from PRE_PROPOSAL_CRITERIA directly,
    // never trusted to a model echo.
    private function buildPreProposalReproductionPrompt(): string
    {
        $wordingOnly = array_map(fn ($c) => [
            'criterion_name' => $c['criterion_name'],
            'category' => $c['category'],
            'description' => $c['description'],
            'levels' => array_map(fn ($lv) => $lv['desc'], $c['levels']),
        ], self::PRE_PROPOSAL_CRITERIA);

        $reference = json_encode([
            'rubric_name' => 'Pre-Proposal Defense Rubric',
            'description' => "Evaluates a capstone team's pre-proposal defense across manuscript delivery, topic quality and proposal merit, and proposal content and clarity.",
            'criteria' => $wordingOnly,
        ], JSON_PRETTY_PRINT);

        return <<<PROMPT
        Reproduce the following capstone Pre-Proposal Defense Rubric exactly as given
        below - do not change any wording, criterion names, or category names, and do
        not add, remove, or reorder criteria. Some criteria intentionally repeat the
        same sentence across all four performance levels; keep that repetition exactly
        as given, do not invent varied wording for those.

        {$reference}

        Respond with ONLY this JSON object, no markdown code fences, no commentary.
        PROMPT;
    }

    // Feeds scoreTitleDefenseCriteria() one "raw criterion" entry per real
    // criterion, built directly from TITLE_DEFENSE_CRITERIA instead of an
    // actual AI echo (see the comment in aiGenerate() for why: a real
    // reproduction call was tested and silently dropped 16 of 27 criteria).
    // Only criterion_name and category are included - scoreTitleDefenseCriteria()
    // uses those two purely to look up the matching real row, then takes
    // every actual value (description, levels, scores) from that row, never
    // from this array - the same division of labor as scorePreProposalCriteria().
    private function titleDefenseCriteriaWordingOnly(): array
    {
        return array_map(fn ($c) => [
            'criterion_name' => $c['criterion_name'],
            'category' => $c['category'],
        ], self::TITLE_DEFENSE_CRITERIA);
    }

    // The AI's echo is only trusted for wording. Point values always come
    // straight from PRE_PROPOSAL_CRITERIA (matched by criterion name, or by
    // position if a name doesn't match - the model was asked for all 12 in
    // order), so the draft's numbers are guaranteed to be the school's real
    // ones regardless of how faithfully the model reproduced them.
    private function scorePreProposalCriteria(array $rawCriteria): array
    {
        $reference = self::PRE_PROPOSAL_CRITERIA;
        $byName = [];

        foreach ($reference as $ref) {
            $byName[$this->normalizeCategoryName($ref['criterion_name'])] = $ref;
        }

        $result = [];

        foreach (array_values($rawCriteria) as $i => $c) {
            $name = $this->normalizeCategoryName($c['criterion_name'] ?? null);
            $ref = ($name !== null ? $byName[$name] ?? null : null) ?? $reference[$i] ?? null;

            if ($ref === null) {
                continue;
            }

            $levels = array_map(fn ($key, $label) => [
                'label' => $label,
                'score' => $ref['levels'][$key]['score'],
                'desc' => $ref['levels'][$key]['desc'],
            ], self::LEVEL_KEYS, self::LEVEL_LABELS);

            $result[] = [
                'criterion_name' => $ref['criterion_name'],
                'description' => $ref['description'],
                'category' => $ref['category'],
                'category_max_points' => $this->categoryMaxPointsFor($ref['category']),
                'max_points' => $ref['levels']['excellent']['score'],
                'levels' => $levels,
                'sort_order' => $i,
            ];
        }

        return $result;
    }

    // Same idea as scorePreProposalCriteria() - wording comes from the AI's
    // echo, point values always come straight from TITLE_DEFENSE_CRITERIA.
    // Level keys/labels are derived from the reference row itself
    // (array_keys/ucfirst) rather than the hardcoded LEVEL_KEYS/LEVEL_LABELS
    // constants, since this rubric has 3 tiers (excellent/fair/poor) and
    // LEVEL_KEYS assumes 4.
    private function scoreTitleDefenseCriteria(array $rawCriteria): array
    {
        $reference = self::TITLE_DEFENSE_CRITERIA;
        $byKey = [];

        // Keyed by category+name, not name alone - the real rubric
        // legitimately reuses "Notes Page" as a criterion name in Chapter 1,
        // 2, AND 3 (each chapter ends with its own notes-page row). A
        // name-only key collapses all three into whichever is listed last,
        // which was confirmed by direct testing to skew Chapter 1/2/3's
        // totals to 19/13/22 instead of the correct 20/15/20 before this
        // category-scoped key was added.
        foreach ($reference as $ref) {
            $key = $this->normalizeCategoryName($ref['category']).'|'.$this->normalizeCategoryName($ref['criterion_name']);
            $byKey[$key] = $ref;
        }

        $result = [];

        foreach (array_values($rawCriteria) as $i => $c) {
            $name = $this->normalizeCategoryName($c['criterion_name'] ?? null);
            $category = $this->normalizeCategoryName($c['category'] ?? null);
            $key = ($name !== null && $category !== null) ? $category.'|'.$name : null;
            $ref = ($key !== null ? $byKey[$key] ?? null : null) ?? $reference[$i] ?? null;

            if ($ref === null) {
                continue;
            }

            $levelKeys = array_keys($ref['levels']);

            $levels = array_map(fn ($key) => [
                'label' => ucfirst($key),
                'score' => $ref['levels'][$key]['score'],
                'desc' => $ref['levels'][$key]['desc'],
            ], $levelKeys);

            $result[] = [
                'criterion_name' => $ref['criterion_name'],
                'description' => $ref['description'],
                'category' => $ref['category'],
                'category_max_points' => $this->categoryMaxPointsFor($ref['category'], 'title_defense'),
                'max_points' => $ref['levels'][$levelKeys[0]]['score'],
                'levels' => $levels,
                'sort_order' => $i,
            ];
        }

        return $result;
    }

    private function categoryMaxPointsFor(string $categoryName, string $rubricTypeSlug = 'pre_proposal'): float
    {
        foreach ($this->defaultCategoriesFor($rubricTypeSlug) as $cat) {
            if ($cat['name'] === $categoryName) {
                return (float) $cat['weight'];
            }
        }

        return 0.0;
    }

    private function buildRubricPrompt(string $typeLabel, int $criteriaCount, string $topic, ?array $fixedCategories): string
    {
        $topicLine = $topic !== '' ? "Focus area / program context: {$topic}\n" : '';
        $examples = json_encode($this->realStyleExamples(), JSON_PRETTY_PRINT);

        if ($fixedCategories) {
            $categoryNames = implode(', ', array_map(fn ($c) => '"'.$c['name'].'"', $fixedCategories));
            $categoryInstruction = <<<TXT
            Group the criteria under exactly these categories (use these exact names verbatim, do not invent new ones or add a "categories" field of your own): {$categoryNames}.
            Distribute the {$criteriaCount} criteria across these categories as evenly as makes sense for this evaluation, and set each criterion's "category" field to exactly one of these names.
            TXT;
            $categoriesShape = '';
        } else {
            // A 3-criteria request paired with "2 to 4 categories" was
            // confusing the model into just giving up and using 1 category
            // for everything (confirmed by reproducing it directly against
            // the real model). Scaling the requested range down to something
            // achievable for the actual criteria count is still the right
            // instruction to give - but re-testing showed the model can
            // still ignore it and return 1 category anyway at low counts.
            // extractCategories() no longer discards that single category to
            // null when it happens, so the AI's real grouping (even if it's
            // just one) is preserved and shown instead of hidden.
            $minCategories = min(2, $criteriaCount);
            $maxCategories = max($minCategories, min(4, $criteriaCount - 1));
            $categoryInstruction = <<<TXT
            Group the criteria into {$minCategories} to {$maxCategories} meaningful categories of your own choosing (broad themes this evaluation covers). You MUST use more than one category - even with only {$criteriaCount} criteria, do not put every criterion under a single category. Include a top-level "categories" array with each category's relative importance as a weight, and set each criterion's "category" field to match one of these names exactly.
            TXT;
            $categoriesShape = <<<'TXT'

              "categories": [
                { "name": "category name", "weight": 40 }
              ],
            TXT;
        }

        return <<<PROMPT
        You are drafting a capstone project grading rubric for a "{$typeLabel}" evaluation.
        {$topicLine}Produce exactly {$criteriaCount} distinct scoring criteria appropriate for this evaluation stage.

        {$categoryInstruction}

        Here are real criteria from this school's Pre-Proposal Defense rubric - a DIFFERENT
        evaluation stage from the "{$typeLabel}" you are drafting now. They are a STYLE reference
        only (match this level of specificity and academic tone), NOT content to reuse:
        {$examples}

        Do NOT copy these example criteria verbatim or near-verbatim (e.g. do not just reuse
        "Slide Usage", "Oral Delivery", "Team Readiness and Presentability", or "Panel Q&A Handling"
        as your own criteria names). Every criterion you write must be an ORIGINAL criterion
        genuinely specific to what a "{$typeLabel}" evaluation assesses.

        Do NOT write vague, hedging, AI-sounding filler. Specifically avoid patterns like:
        - "While [X], there are [some/minor] areas where [Y] could be more [Z]"
        - "could be more concise/specific/clear"
        - "in general", "overall", "for the most part", "somewhat", "relatively"
        - describing what's merely "acceptable" or "adequate" without saying what was actually observed
        Instead, describe an observable, concrete outcome the way the real examples above do -
        state what was or wasn't done/shown/covered, not a soft opinion about how it could be improved.

        Respond with ONLY raw JSON, no markdown code fences, no commentary, matching this exact shape:
        {
          "rubric_name": "short rubric title",
          "description": "one or two sentence description of what this rubric evaluates",{$categoriesShape}
          "criteria": [
            {
              "criterion_name": "short criterion name",
              "category": "must match one of the category names above",
              "description": "one sentence describing what this criterion assesses",
              "levels": {
                "excellent": "one sentence describing excellent performance on this criterion",
                "good": "one sentence describing good performance on this criterion",
                "fair": "one sentence describing fair performance on this criterion",
                "poor": "one sentence describing poor performance on this criterion"
              }
            }
          ]
        }

        Do not include point values or scores anywhere - only names, categories, and descriptions. Output the JSON object and nothing else.
        PROMPT;
    }

    // LLMs frequently wrap JSON in ```json fences or add a sentence before
    // it despite instructions not to - pull out the first {...} block
    // rather than assuming the whole response is clean JSON.
    private function extractRubricJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    // Validates the AI's proposed category list ({name, weight}); returns
    // null (triggering the flat, unnamed single-category fallback in
    // scoreCriteriaWithCategories) only if NO valid category survives.
    // Previously required >= 2, silently discarding the AI's real category
    // name whenever it settled on just one (confirmed happening routinely
    // with low criteria counts against the real model, regardless of prompt
    // wording asking for more) - that turned "1 real category" into "0
    // categories shown at all", which is strictly worse.
    private function extractCategories(mixed $categories): ?array
    {
        if (! is_array($categories) || empty($categories)) {
            return null;
        }

        $clean = [];

        foreach ($categories as $c) {
            if (! is_array($c) || ! isset($c['name']) || ! is_string($c['name']) || trim($c['name']) === '') {
                continue;
            }

            if (! isset($c['weight']) || ! is_numeric($c['weight']) || (float) $c['weight'] <= 0) {
                continue;
            }

            $clean[] = ['name' => trim($c['name']), 'weight' => (float) $c['weight']];
        }

        return count($clean) >= 1 ? $clean : null;
    }

    // Groups the raw criteria under the given categories (or a single
    // implicit category spanning everything, if $categories is null - the
    // original flat behavior), then splits 100 points across categories by
    // weight, each category's budget across its own criteria, and each
    // criterion's budget across its 4 levels. All pure integer math with
    // largest-remainder rounding, so the grand total is exactly 100 no
    // matter how the model grouped things or how many entries end up where.
    private function scoreCriteriaWithCategories(array $rawCriteria, ?array $categories): array
    {
        if (empty($rawCriteria)) {
            return [];
        }

        $categories = $categories ?: [['name' => null, 'weight' => 1]];
        $names = array_map(fn ($c) => $this->normalizeCategoryName($c['name'] ?? null), $categories);

        $buckets = array_fill(0, count($categories), []);

        foreach (array_values($rawCriteria) as $i => $c) {
            $catName = $this->normalizeCategoryName($c['category'] ?? null);
            $idx = $catName !== null ? array_search($catName, $names, true) : false;

            // Unmatched/missing category (or the single implicit category,
            // where $catName is always null) round-robins across the
            // category list instead of being dropped.
            if ($idx === false) {
                $idx = $i % count($categories);
            }

            $buckets[$idx][] = $c;
        }

        // Only categories that actually received a criterion get a point
        // budget - weights are renormalized across just those so the grand
        // total still lands on exactly 100 even if the model left one empty.
        $usedIdx = array_keys(array_filter($buckets, fn ($b) => ! empty($b)));

        if (empty($usedIdx)) {
            return [];
        }

        $usedWeights = array_map(fn ($i) => (float) ($categories[$i]['weight'] ?? 1), $usedIdx);
        $categoryBudgets = $this->allocateByWeights($usedWeights, 100);

        $result = [];
        $sortOrder = 0;

        foreach (array_values($usedIdx) as $pos => $catIdx) {
            $categoryBudget = $categoryBudgets[$pos];
            $categoryName = $categories[$catIdx]['name'] ?? null;
            $criteriaInCategory = $buckets[$catIdx];

            $perCriterionBudgets = $this->allocateByWeights(
                array_fill(0, count($criteriaInCategory), 1),
                $categoryBudget
            );

            foreach (array_values($criteriaInCategory) as $j => $c) {
                $budget = $perCriterionBudgets[$j];
                $levels = is_array($c['levels'] ?? null) ? $c['levels'] : [];

                $result[] = [
                    'criterion_name' => trim((string) ($c['criterion_name'] ?? 'Criterion '.($sortOrder + 1))),
                    'description' => trim((string) ($c['description'] ?? '')),
                    'category' => $categoryName,
                    'category_max_points' => $categoryName !== null ? $categoryBudget : null,
                    'max_points' => $budget,
                    'levels' => $this->levelsForBudget($budget, $levels),
                    'sort_order' => $sortOrder,
                ];
                $sortOrder++;
            }
        }

        return $result;
    }

    private function normalizeCategoryName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        return $trimmed === '' ? null : mb_strtolower($trimmed);
    }

    // Distributes $total across entries proportional to $weights using
    // largest-remainder rounding (floor each share, then hand out the
    // leftover one point at a time to the entries with the biggest
    // fractional part) - the standard way to round percentages so they
    // still sum to exactly the target instead of drifting off by rounding.
    private function allocateByWeights(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return array_fill(0, $n, 0);
        }

        $sumWeights = array_sum($weights);

        if ($sumWeights <= 0) {
            $weights = array_fill(0, $n, 1);
            $sumWeights = $n;
        }

        $raw = array_map(fn ($w) => $total * $w / $sumWeights, $weights);
        $floors = array_map(fn ($r) => (int) floor($r), $raw);
        $remainder = $total - array_sum($floors);

        $fractions = [];
        foreach ($raw as $i => $r) {
            $fractions[$i] = $r - $floors[$i];
        }
        arsort($fractions);
        $orderedKeys = array_keys($fractions);

        for ($i = 0; $i < $remainder; $i++) {
            $floors[$orderedKeys[$i % $n]]++;
        }

        return $floors;
    }

    private function summarizeCategories(array $scoredCriteria): array
    {
        $seen = [];
        $result = [];

        foreach ($scoredCriteria as $c) {
            $name = $c['category'];

            if ($name === null || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $result[] = ['name' => $name, 'max_points' => $c['category_max_points']];
        }

        return $result;
    }

    // "Excellent" is the criterion's full budget (its max_points ceiling) -
    // a panel picks exactly ONE of these four values as the criterion's
    // score, so Good/Fair/Poor are lower ALTERNATIVE scores for the same
    // criterion, not additional points stacked on top of it. Ratios match
    // the pattern in the school's real rubric (Manuscript section: exactly
    // 100/75/50/25% of each row's ceiling).
    private function levelsForBudget(int $budget, array $levelDescriptions): array
    {
        $ratios = [1.0, 0.75, 0.5, 0.25];

        return array_map(function ($key, $label, $ratio) use ($levelDescriptions, $budget) {
            return [
                'label' => $label,
                'score' => round($budget * $ratio, 2),
                'desc' => trim((string) ($levelDescriptions[$key] ?? '')),
            ];
        }, self::LEVEL_KEYS, self::LEVEL_LABELS, $ratios);
    }

    public function index(): JsonResponse
    {
        $rubrics = GradingRubric::with('criteria')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->formatRubric($r));

        return response()->json([
            'success' => true,
            'message' => 'Rubrics retrieved successfully.',
            'data' => $rubrics,
        ]);
    }

    public function show(GradingRubric $gradingRubric): JsonResponse
    {
        $gradingRubric->load('criteria');

        return response()->json([
            'success' => true,
            'message' => 'Rubric retrieved successfully.',
            'data' => $this->formatRubric($gradingRubric),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rubric_name' => ['required', 'string', 'max:255'],
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_points' => $this->totalPointsRule(),
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $rubric = GradingRubric::create([
            'rubric_name' => $validated['rubric_name'],
            'rubric_type' => $dbType,
            'description' => $validated['description'] ?? null,
            'total_points' => $validated['total_points'] ?? 100.00,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rubric created successfully.',
            'data' => $this->formatRubric($rubric),
        ], 201);
    }

    public function update(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'rubric_name' => ['required', 'string', 'max:255'],
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_points' => $this->totalPointsRule(),
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $gradingRubric->update([
            'rubric_name' => $validated['rubric_name'],
            'rubric_type' => $dbType,
            'description' => $validated['description'] ?? null,
            'total_points' => $validated['total_points'] ?? 100.00,
            // Editing an archived rubric must not quietly put it back into use -
            // restoring it is the only way out of the archive.
            'is_active' => $gradingRubric->is_archived ? false : ($validated['is_active'] ?? true),
        ]);

        $gradingRubric->load('criteria');

        return response()->json([
            'success' => true,
            'message' => 'Rubric updated successfully.',
            'data' => $this->formatRubric($gradingRubric),
        ]);
    }

    public function destroy(GradingRubric $gradingRubric): JsonResponse
    {
        $gradingRubric->is_archived = true;
        $gradingRubric->is_active = false;
        $gradingRubric->save();

        return response()->json([
            'success' => true,
            'message' => 'Rubric archived successfully.',
        ]);
    }

    public function restore(GradingRubric $gradingRubric): JsonResponse
    {
        $gradingRubric->is_archived = false;
        $gradingRubric->is_active = true;
        $gradingRubric->save();

        return response()->json([
            'success' => true,
            'message' => 'Rubric restored successfully.',
        ]);
    }

    public function storeCriterion(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'criterion_name' => $this->criterionNameRule(),
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:150'],
            'category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => $this->scoreLevelsRule(),
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // max_points always comes from the levels themselves (never trusted
        // as an independent client-supplied value) - it's the ceiling of
        // whichever level scores highest, normally "Excellent".
        $newMaxPoints = $this->scoreLevelsMax($validated['score_levels'] ?? null);

        // Exactly 100 is fine - a rubric may be one all-or-nothing criterion.
        // Over 100 is not, since the rest could only be scaled to negatives.
        if ($newMaxPoints > 100.0) {
            return response()->json([
                'success' => false,
                'message' => 'A criterion cannot be worth more than the 100 points a rubric totals.',
            ], 422);
        }

        // Adding a criterion used to be refused unless the rubric already
        // added up to 100 with it, which meant hand-reducing other criteria
        // first just to be allowed to save. The existing criteria are now
        // scaled down to make room instead.
        $rebalanced = $this->rebalanceCriteria($gradingRubric->criteria()->get(), 100.0 - $newMaxPoints);

        $criterion = $gradingRubric->criteria()->create([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
            'max_points' => $newMaxPoints,
            'score_levels' => $validated['score_levels'] ?? '[]',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => $rebalanced > 0
                ? "Criterion created. {$rebalanced} existing criteria were scaled down to keep the rubric at 100 points."
                : 'Criterion created successfully.',
            'data' => $this->formatCriterion($criterion),
            'rebalanced_count' => $rebalanced,
            'criteria' => $this->formatCriteria($gradingRubric->criteria()->orderBy('sort_order')->get()),
        ], 201);
    }

    public function updateCriterion(Request $request, GradingRubric $gradingRubric, RubricCriterion $rubricCriterion): JsonResponse
    {
        if ($rubricCriterion->rubric_id !== $gradingRubric->rubric_id) {
            return response()->json([
                'success' => false,
                'message' => 'Criterion does not belong to this rubric.',
            ], 404);
        }

        $validated = $request->validate([
            'criterion_name' => $this->criterionNameRule(),
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:150'],
            'category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => $this->scoreLevelsRule(),
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $newMaxPoints = $this->scoreLevelsMax($validated['score_levels'] ?? null);

        // Exactly 100 is fine - a rubric may be one all-or-nothing criterion.
        // Over 100 is not, since the rest could only be scaled to negatives.
        if ($newMaxPoints > 100.0) {
            return response()->json([
                'success' => false,
                'message' => 'A criterion cannot be worth more than the 100 points a rubric totals.',
            ], 422);
        }

        // Changing what a criterion is worth makes room the same way adding
        // one does: everything else scales to fill the remaining points.
        $others = $gradingRubric->criteria()->where('criterion_id', '!=', $rubricCriterion->criterion_id)->get();
        $rebalanced = $this->rebalanceCriteria($others, 100.0 - $newMaxPoints);

        $rubricCriterion->update([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
            'max_points' => $newMaxPoints,
            'score_levels' => $validated['score_levels'] ?? '[]',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => $rebalanced > 0
                ? "Criterion updated. {$rebalanced} other criteria were scaled to keep the rubric at 100 points."
                : 'Criterion updated successfully.',
            'data' => $this->formatCriterion($rubricCriterion->fresh()),
            'rebalanced_count' => $rebalanced,
            'criteria' => $this->formatCriteria($gradingRubric->criteria()->orderBy('sort_order')->get()),
        ]);
    }

    public function destroyCriterion(GradingRubric $gradingRubric, RubricCriterion $rubricCriterion): JsonResponse
    {
        if ($rubricCriterion->rubric_id !== $gradingRubric->rubric_id) {
            return response()->json([
                'success' => false,
                'message' => 'Criterion does not belong to this rubric.',
            ], 404);
        }

        $rubricCriterion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Criterion deleted successfully.',
        ]);
    }

    public function syncCriteria(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'criteria' => ['required', 'array'],
            'criteria.*.criterion_name' => $this->criterionNameRule(),
            'criteria.*.max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.category' => ['nullable', 'string', 'max:150'],
            'criteria.*.category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'criteria.*.score_levels' => $this->scoreLevelsRule(),
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Not a hard 100 any more. Refusing the save was what made a real
        // rubric painful to enter: a 27-criterion rubric is typed one
        // criterion at a time, so it is under 100 for the entire time it is
        // being built, and there was no way to stop halfway. The total is
        // recorded as-is and the page says when it is off.

        return DB::transaction(function () use ($gradingRubric, $validated): JsonResponse {
            $gradingRubric->criteria()->delete();

            foreach ($validated['criteria'] as $i => $c) {
                $gradingRubric->criteria()->create([
                    'criterion_name' => trim($c['criterion_name']),
                    'description' => $c['description'] ?? null,
                    'category' => $c['category'] ?? null,
                    'category_max_points' => $c['category_max_points'] ?? null,
                    'max_points' => $this->scoreLevelsMax($c['score_levels'] ?? null),
                    'score_levels' => $c['score_levels'] ?? '[]',
                    'sort_order' => $c['sort_order'] ?? $i,
                ]);
            }

            $gradingRubric->load('criteria');

            // Keep the rubric's declared total honest - it is the denominator a
            // score is out of, so it has to be what the criteria really sum to.
            $gradingRubric->update([
                'total_points' => round($this->criteriaMaxPointsTotal($gradingRubric->criteria()->get()), 2),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Criteria synced successfully.',
                'data' => $this->formatRubric($gradingRubric),
            ]);
        });
    }

    private function totalPointsRule(): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            'max:999999.99',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && (float) $value !== 100.0) {
                    $fail('Total Criteria points must equal exactly 100 points.');
                }
            },
        ];
    }

    private function criterionNameRule(): array
    {
        return [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || trim($value) === '') {
                    $fail('Criterion Name is required for every criterion.');
                }
            },
        ];
    }

    private function scoreLevelsRule(): array
    {
        return [
            'nullable',
            'json',
            function (string $attribute, mixed $value, Closure $fail): void {
                $levels = json_decode($value, true);

                if (! is_array($levels)) {
                    return;
                }

                foreach ($levels as $level) {
                    if (! is_array($level) || ! array_key_exists('label', $level)) {
                        continue;
                    }

                    if (! is_string($level['label']) || mb_strlen($level['label']) > 255) {
                        $fail('Each score level label must be a string of at most 255 characters.');
                    }

                    if (! array_key_exists('desc', $level) || ! is_string($level['desc']) || trim($level['desc']) === '') {
                        $fail('Performance level description is required for every performance level.');
                    }
                }
            },
        ];
    }

    // A criterion is scored by picking exactly ONE of its performance
    // levels - its max_points ceiling is the highest of those levels
    // (normally "Excellent"), not their sum. Used to auto-derive max_points
    // when a request doesn't supply it explicitly.
    /**
     * Scale a set of criteria so their ceilings add up to $targetTotal.
     *
     * Each criterion's max_points is the highest of its own score levels, so
     * the levels are what actually move - scaling the number alone would
     * leave a criterion claiming a ceiling none of its levels can reach, and
     * grading validates every submitted score against those level values.
     *
     * Rounding is to 2dp (the column's precision) and the remainder is
     * absorbed by the largest criterion, so the rubric lands on exactly
     * $targetTotal rather than 99.98.
     *
     * @param  Collection<int, RubricCriterion>  $criteria
     * @return int how many criteria were changed
     */
    private function rebalanceCriteria($criteria, float $targetTotal): int
    {
        $current = $this->criteriaMaxPointsTotal($criteria);

        // Nothing to scale, or already right. A rubric whose criteria total
        // zero has no proportions to preserve, so leave it be rather than
        // divide by zero.
        if ($criteria->isEmpty() || $current <= 0.0 || abs($current - $targetTotal) < 0.0001) {
            return 0;
        }

        if ($targetTotal <= 0.0) {
            return 0;
        }

        $factor = $targetTotal / $current;
        $changed = 0;
        $scaled = [];

        foreach ($criteria as $criterion) {
            $levels = $criterion->decodedScoreLevels();

            foreach ($levels as $i => $level) {
                if (is_array($level) && isset($level['score']) && is_numeric($level['score'])) {
                    // Snapped to a quarter point, the same step the score
                    // inputs use. Plain scaling turned a tidy 3 / 5 / 10
                    // rubric into 3.07 / 5.12 / 10.23.
                    $levels[$i]['score'] = round((float) $level['score'] * $factor * 4) / 4;
                }
            }

            $scaled[] = [
                'criterion' => $criterion,
                'levels' => $levels,
                'max' => $this->scoreLevelsMax($levels),
            ];
        }

        // Hand out whatever the rounding left over a quarter at a time,
        // largest first, rather than dumping it all on one criterion - which
        // sent a single criterion from 10 to 11.75 absorbing everyone else's.
        $drift = round($targetTotal - array_sum(array_column($scaled, 'max')), 2);
        $step = $drift > 0 ? 0.25 : -0.25;
        $guard = 0;

        // Largest first, and the order is stable across passes.
        $order = array_keys($scaled);
        usort($order, fn ($a, $b) => $scaled[$b]['max'] <=> $scaled[$a]['max']);

        $cursor = 0;
        while (abs($drift) >= 0.01 && $guard++ < 2000) {
            $i = $order[$cursor % count($order)];
            $cursor++;

            if ($step < 0 && $scaled[$i]['max'] <= 0.25) {
                continue;
            }

            $scaled[$i]['levels'] = $this->shiftTopLevel($scaled[$i]['levels'], $step);
            $scaled[$i]['max'] = $this->scoreLevelsMax($scaled[$i]['levels']);
            $drift = round($drift - $step, 2);
        }

        foreach ($scaled as $row) {
            $row['criterion']->update([
                'score_levels' => $row['levels'],
                'max_points' => $row['max'],
            ]);
            $changed++;
        }

        return $changed;
    }

    /** Nudge the highest-scoring level by $delta, keeping the rest as they are. */
    private function shiftTopLevel(array $levels, float $delta): array
    {
        $topIndex = null;

        foreach ($levels as $i => $level) {
            if (! is_array($level) || ! isset($level['score']) || ! is_numeric($level['score'])) {
                continue;
            }

            if ($topIndex === null || (float) $level['score'] > (float) $levels[$topIndex]['score']) {
                $topIndex = $i;
            }
        }

        if ($topIndex !== null) {
            $levels[$topIndex]['score'] = max(0, round((float) $levels[$topIndex]['score'] + $delta, 2));
        }

        return $levels;
    }

    private function scoreLevelsMax(mixed $scoreLevels): float
    {
        $levels = is_array($scoreLevels) ? $scoreLevels : (json_decode((string) $scoreLevels, true) ?? []);

        $scores = collect($levels)
            ->filter(fn ($level) => is_array($level) && isset($level['score']) && is_numeric($level['score']))
            ->map(fn ($level) => (float) $level['score']);

        return $scores->isEmpty() ? 0.0 : (float) $scores->max();
    }

    // The rubric-wide "must total exactly 100" rule sums each criterion's
    // own max_points ceiling - not every level of every criterion, since a
    // panel only ever picks one level per criterion.
    private function criteriaMaxPointsTotal(iterable $criteria): float
    {
        $total = 0.0;

        foreach ($criteria as $criterion) {
            $total += (float) $criterion->max_points;
        }

        return $total;
    }

    private function formatRubric(GradingRubric $rubric): array
    {
        $slug = self::TYPE_MAP_REVERSE[$rubric->rubric_type] ?? $rubric->rubric_type;

        return [
            'rubric_id' => $rubric->rubric_id,
            'rubric_name' => $rubric->rubric_name,
            'rubric_type' => $slug,
            'rubric_type_label' => $rubric->rubric_type,
            'description' => $rubric->description,
            'total_points' => (float) ($rubric->total_points ?? 100),
            'is_active' => (bool) $rubric->is_active,
            'is_archived' => (bool) $rubric->is_archived,
            'criteria' => $rubric->relationLoaded('criteria')
                ? $rubric->criteria->map(fn ($c) => $this->formatCriterion($c))->values()->toArray()
                : [],
            'criteria_count' => $rubric->relationLoaded('criteria') ? $rubric->criteria->count() : 0,
            // What the criteria actually add up to. Adding or editing a
            // criterion rebalances the rest back to 100, but deleting one
            // deliberately does not - silently inflating what everyone else
            // is worth would change grades behind the CAPSA's back - so a
            // rubric can sit below 100 and the page needs to be able to say so.
            'criteria_total' => $rubric->relationLoaded('criteria')
                ? round($this->criteriaMaxPointsTotal($rubric->criteria), 2)
                : null,
            'created_at' => $rubric->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $rubric->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Every criterion of a rubric, formatted.
     *
     * Returned alongside a create or update because rebalancing rewrites the
     * OTHER criteria too - the page would otherwise still be showing their
     * old point values.
     *
     * @param  Collection<int, RubricCriterion>  $criteria
     */
    private function formatCriteria($criteria): array
    {
        return $criteria->map(fn (RubricCriterion $c) => $this->formatCriterion($c))->values()->all();
    }

    private function formatCriterion(RubricCriterion $criterion): array
    {
        $levelsJson = $criterion->score_levels;
        $levels = [];

        if (is_string($levelsJson)) {
            $levels = json_decode($levelsJson, true) ?? [];
        } elseif (is_array($levelsJson)) {
            $levels = $levelsJson;
        }

        foreach ($levels as &$lv) {
            if (isset($lv['score'])) {
                $lv['score'] = is_numeric($lv['score']) ? (float) $lv['score'] : $lv['score'];
            }
        }

        return [
            'criterion_id' => $criterion->criterion_id,
            'rubric_id' => $criterion->rubric_id,
            'criterion_name' => $criterion->criterion_name,
            'description' => $criterion->description,
            'category' => $criterion->category,
            'category_max_points' => $criterion->category_max_points !== null ? (float) $criterion->category_max_points : null,
            'max_points' => (float) $criterion->max_points,
            'score_levels' => $levels,
            'sort_order' => (int) $criterion->sort_order,
        ];
    }
}
