<?php

namespace App\Support;

/**
 * The kinds of project a pre-proposal can be tagged as.
 *
 * A fixed list rather than free text, for the same reason SkillCatalog is one:
 * "Mobile App", "mobile application" and "Android app" typed by three teams
 * are one thing, and only a shared list lets anyone count or filter by it.
 * The form offers these and the server accepts nothing else.
 */
class ProjectTags
{
    public const MIN = 1;

    public const MAX = 5;

    public const OPTIONS = [
        'AI / Machine Learning',
        'Web-based',
        'Management System',
        'Mobile App',
        'Blockchain',
        'IoT',
        'AR / VR',
        'Computer Vision',
        'Data Analytics',
        'Decision Support System',
        'GIS / Mapping',
        'Chatbot / NLP',
        'Game Development',
        'E-commerce',
        'E-learning',
        'Cybersecurity',
        'Desktop Application',
        'Automation',
    ];
}
