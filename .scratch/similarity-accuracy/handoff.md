# AI Similarity Check: Accuracy Fixes — Handoff

Status: ready-for-agent

Written from a cloud session that could read the code but had no Ollama, no database and no archive. Everything under "Findings" was read off the code; nothing was run. Items marked **verify** need the live model or the real archive to confirm.

## What the user reports

1. **Unrelated studies get a high percentage.**
2. **Some matches sit in the top 10 with no percentage.**

The goal is a check whose percentages can be trusted, on a server with **no GPU**. The user's laptop (RTX 3060, 6 GB VRAM) is for development only.

## How the check works today

`PreProposalController` (line 230 onward) runs everything inside one web request:

1. `isPreProposal()`: one LLM call.
2. `extractKeyConcepts()`: reads the form by its headings, no LLM.
3. `findSimilarDocuments()`: embeds the query, cosine against every archive row plus this year's recommended and pending titles, keeps the top 10 archive + top 5 this-year.
4. `judgeCandidates()`: one LLM prompt per candidate, sent together through `generateMany()`.
5. `addExactWords()`: shared-words check, takes over the score at 50% or more.
6. `suggestInstructors()`: more LLM calls.

Models come from `.env`: `OLLAMA_EMBED_MODEL=nomic-embed-text`, `OLLAMA_JUDGE_MODEL=capstrack-duplication-v3` (a fine-tune trained on the exact judge prompt wording).

## Findings

### A. Why unrelated studies score high

| # | Cause | Where |
| --- | --- | --- |
| A1 | The embedding percentage is `(cosine + 1) / 2 × 100`. A cosine of 0.4, ordinary for two unrelated capstones from the same college, becomes 70%. | `SimilarityService.php:659` |
| A2 | The "drop below 40%" floor is therefore cosine −0.2. It removes nothing. `OLLAMA_JUDGE_MIN_SCORE=40` has the same problem. | `SimilarityService.php:662`, `config/services.php:106` |
| A3 | That inflated number is shown as the score when the judge says `matches: true` without a usable score. | `SimilarityService.php:893` |
| A4 | The text sent to the embedding model is lowercased, stripped of punctuation and stop words: a bag of words. Embedding models are trained on sentences, so this throws away what separates "manages students" from "manages inventory". | `removeStopWords()` line 367, `GenerateEmbeddings::cleanText()` |
| A5 | `nomic-embed-text` expects a task prefix (`search_document:`, `clustering:` and so on). None is sent. | `OllamaSSHClient::embed()` line 430 |
| A6 | The two sides are different shapes. Query: title + first sentence + domain + keywords + problem + solution + scope. Archive: title + abstract. This-year titles: title + problem + solution. | lines 606, 795; `GenerateEmbeddings.php:77` |
| A7 | The judge is a small model asked for a free 0 to 100 number. `config/services.php:72` already records that the fine-tune "recites one stock verdict on some cases". Three of the four worked examples in the prompt are about CapsTrack itself. | `buildJudgePrompt()` line 1108 |
| A8 | This-year pre-proposals are shown to the judge under the labels "ARCHIVED DOCUMENT" and "Abstract". | lines 808, 1202 |
| A9 | **verify:** no `num_ctx` is sent. The judge prompt is roughly 2,300 to 2,800 tokens plus 700 for the answer. If the model runs at 2,048, Ollama silently cuts the start of the prompt, which is where the rules are. Check `ollama show capstrack-duplication-v3` and the `prompt_eval_count` of a real reply. | `OllamaSSHClient::generate()` line 253 |

### B. Why a match has no percentage

A match shows "Not scored" whenever `assessed` is false, which means the judge returned nothing readable (`SimilarityBand::scoreOf()`, `_report-row.blade.php:12`). That is deliberate. The fault is how often it happens and where the row lands.

| # | Cause | Where |
| --- | --- | --- |
| B1 | No `format` is sent to Ollama, so the model writes JSON freehand. `repairJson()` and `firstJsonObject()` patch two known slips; anything else becomes a null verdict. | `readVerdict()` line 1215 |
| B2 | Up to 15 judge prompts go out at once, each with a 300-second timeout. On a CPU-only server they queue behind `OLLAMA_NUM_PARALLEL`, and the ones at the back time out while waiting. **verify** by logging why each verdict was null. | `generateMany()` line 305 |
| B3 | An unjudged row is still ranked by `embedding × 0.4`. With A1, that is about 30, so it sits above every judged row the model scored under 30. This is the "in the top 10 with no percentage" the user sees. | `SimilarityService.php:895`, 906 |
| B4 | **verify:** the server's `.env` may set `OLLAMA_JUDGE_LIMIT` below 10, which leaves the rest unjudged by design. | `config/services.php:102` |

### C. Other gaps

| # | Gap | Where |
| --- | --- | --- |
| C1 | Nothing embeds an archive row when it is created or edited. A new archive is invisible to the check until someone runs `embeddings:generate`, and an edited abstract keeps its old vector. | `Admin/ArchiveController.php:53`, `Student/ArchiveController.php:102` |
| C2 | No column records which model made a vector, and `cosineSimilarity()` compares only the first `min(count)` numbers. Vectors from two different models would be compared without any error. | line 1251 |
| C3 | The stop-word list is kept in two places that must match and already do not: the command also drops numeric tokens. | `GenerateEmbeddings.php:23`, 123 |
| C4 | When the embed call fails, or fewer than 5 rows have vectors, the check silently switches to `findSimilarDocumentsLegacy()`. That path judges 10 documents per prompt (the bias line 820 says to avoid), cuts abstracts to 150 characters, skips this-year titles and the exact-words check, and returns rows without `assessed`, so every row shows a percentage. | line 1267 |
| C5 | The verdict uses 65 / 55 while the bands use 65 / 40. | `PreProposalController.php:256`, `SimilarityBand.php` |
| C6 | There is no way to measure accuracy. `storage/app/training-data/` holds 167 similarity examples and a 15-item held-out set, but their answers came from the 7B model, not from a person. | |
| C7 | `isPreProposal()` costs an LLM call on every scan, and its prompt lists "a form" as not a pre-proposal although the document is a form. A scan whose headings were all found could skip it. | line 315 |
| C8 | The whole check runs inside the request under `set_time_limit(300)`. On CPU that is the first thing to break. | `PreProposalController.php:227` |

## Plan

Do the phases in order. Each one is measured with the Phase 0 command before and after.

### Phase 0: Measure before changing anything

1. Build a **gold set** of 30 to 40 pairs labelled by a person (the user or their adviser): pre-proposal text, the other study, and one of `duplicate` / `related` / `unrelated`. Include this-year pairs, not only archive pairs. Keep it as a JSON fixture under `tests/`.
2. Add `php artisan duplication:evaluate`. For a given embed model and judge model it prints:
   - retrieval: is the known duplicate inside the top 10 (recall@10)?
   - separation: the cosine ranges of the three labels, and how much they overlap;
   - judge: agreement with the label, and how many verdicts came back null and why;
   - seconds per check.
3. Log the reason for every null verdict (timeout, unparseable, empty) to settle B2 and B4.

### Phase 1: Stop "Not scored" (no model change)

1. Send `format` with a JSON schema on judge calls, so a reply always parses. Keep `readVerdict()` as the fallback.
2. Send an explicit `num_ctx` large enough for the prompt (A9).
3. Send judge prompts in groups the server can answer inside the timeout, not 15 at once.
4. Sort unjudged rows after judged ones (B3).
5. Skip `isPreProposal()` when `splitPreProposalForm()` found the headings (C7).
6. Mark legacy rows `assessed => false` (C4).

### Phase 2: Fix the embedding stage, and the bge-m3 vs Qwen test

1. Embed natural text. Keep `redactLocations()`; stop stripping stop words and punctuation.
2. Build both sides the same way. Archive: title + abstract. Pre-proposal: title + problem statement + proposed solution + scope. Leave out domain, keywords, limitations, SDG, research agenda and SDM.
3. Add an `embedding_model` column to `archived_documents` and `capstone_pre_proposals`. Skip any vector whose model or length differs from the current one, and log it (C2).
4. Make `embeddings:generate` cover both tables and share one text builder with the service (C3). Embed on archive create and update (C1).
5. **The model test the user asked for.** Run `duplication:evaluate` on the gold set with:
   - `nomic-embed-text` as today (baseline),
   - `nomic-embed-text` with natural text and its task prefix,
   - `bge-m3`,
   - `qwen3-embedding:0.6b`, document against document with no instruction, and again with its instruction on the query side.

   Pick the one with the widest gap between `duplicate` and `unrelated`, then recall@10, then CPU speed. Record the numbers in this file.
6. Replace `(cosine + 1) / 2` and the two 40 floors with cut-offs read off the gold set for the chosen model. The embedding number orders candidates; it is never shown as a similarity percentage.

### Phase 3: Make the judge's number trustworthy

1. Ask the judge for answers, not a number: same subject (yes / partly / no), same users, same core features, plus the one-sentence reason, similarities, differences and suggestion. Code turns the answers into the score, so the same answers always give the same percentage.
2. Give this-year titles their own prompt, comparing field to field under the right labels (A8).
3. **The fine-tune only knows the current prompt.** A changed prompt means retraining it or moving to a base model. Compare on the gold set, on CPU: the current fine-tune with the current prompt, against `qwen2.5:3b`, `qwen3:4b` (thinking off) and `gemma3:4b` with the new prompt.
4. Use the bands' thresholds for the verdict (C5).

### Phase 4: Clean up

1. Replace the legacy fallback with a plain "the checker is not available, try again" message, or keep it for retrieval only with no percentages (C4).
2. Move the check to a queued job if Phase 0 shows a check cannot finish inside the request on the server (C8).
3. Update the README sections on the checker to match.

## Rules for whoever picks this up

- Another session is committing to `capstrack_with_gdrive`. Work on a separate branch and merge when it is quiet.
- Follow `AGENTS.md`: PHPUnit tests for every change, `vendor/bin/pint --dirty --format agent`, no new dependencies without approval.
- These tests pin the current behaviour and will need updating, not deleting: `SimilarityServiceScoreCappingTest`, `SimilarityJudgeBatchTest`, `SimilarityVerdictParsingTest`, `UnjudgedMatchShowsNoScoreTest`, `ScoreWorkedOutTest`, `RecommendedTitlesInDuplicationTest`, `PendingTitlesInDuplicationTest`, `ExactWordsInDuplicationTest`, `SimilarityReportExplainsItselfTest`.
- The match page (`student/duplication/similar.blade.php`, "Score explanation" tab) spells out the current arithmetic, including `× 0.4`. Change it with the scoring.
- Changing the embedding model or the text builder means re-embedding every archive row and clearing `capstone_pre_proposals.embedding`.

## Decisions the user still owes

1. Who labels the gold set, and by when.
2. The server's cores and RAM, and its current `OLLAMA_JUDGE_LIMIT` and `OLLAMA_NUM_PARALLEL`.
3. Keep and retrain the fine-tune, or move to a base model (Phase 3.3 gives the numbers for this).
4. What an unjudged match should show: "Not scored" as now, or be left out of the list.

## Comments

**2026-11-15.** Phase 0 step 2 is in: `php artisan duplication:evaluate` scores each labelled pair with `SimilarityService::scorePair()`, the same embedding text, judge prompt and score rules a real check uses, and prints false positives, missed duplicates, the cosine range per label, unscored pairs and seconds per pair. It works pair by pair, so it needs Ollama but not the archive; recall@10 against the archive is not measured yet. The gold set itself (step 1) is still owed: copy `tests/Fixtures/duplication-gold-set.example.json` to `tests/Fixtures/duplication-gold-set.json` and replace its three made-up pairs with 30 to 40 labelled by a person. No numbers have been run: the session that wrote the command had no Ollama. Decision 3 is settled by commits `8afbe2a` and `ad9ebfc`: the judge is now the base Qwen 3.5 4B, not the fine-tune.

**2026-11-15, later.** A draft gold set is in `tests/Fixtures/duplication-gold-set.json`: 12 made-up pre-proposals, each paired with a duplicate, a related and an unrelated study from the real archive (36 pairs). The other study is named by title only and `duplication:evaluate` reads its abstract from the archive, so the command now needs the archive as well as Ollama. **The labels are a draft written by the agent from the archive titles alone, without reading the abstracts.** Each pair carries a `why` line; a person (the user or their adviser) still has to read the 36 and correct any label before the numbers are quoted as measured against human labels. The pre-proposals are also cleaner than real student ones, so the result is likely kinder than real use.

**2026-11-15, first measurement.** The user ran `duplication:evaluate` twice on the draft gold set (36 pairs, Qwen 3.5 4B judge, nomic-embed-text); the two runs agreed except one score moving 12 to 15.

| | False positives | Missed duplicates |
| --- | --- | --- |
| Score shown | 0 of 24 | 3 of 12 |
| Embedding alone at 65% | 24 of 24 | 0 of 12 |

Cosine by label: duplicate 0.74 to 0.91, related 0.52 to 0.76, unrelated 0.44 to 0.57. Duplicate and unrelated do not overlap, so retrieval orders well and A1 (the `(cos + 1) / 2` percentage) is what is wrong with step 1, not the model. Every non-duplicate scored 0 to 15 and every caught duplicate 75 to 95: nothing landed between, so the "Some overlap" band is unused.

The three misses (health unit, alumni, water refilling, all 15%) were the judge calling a narrower or wider scope a different thing: "Both systems track graduates but one focuses solely on employment verification while the other manages a full lifecycle". So Phase 3 step 1 is now in: the judge answers `same_things`, `same_users`, `same_features` with yes / partly / no and `SimilarityService::rubricScore()` works the score out. The model's own number is still asked for and kept as `own_score`, so one run of the command compares the two ("Score shown" against "The model's own number"). A reply without the answers is read by its own number as before. The answers are saved with each match and shown on the match page's Score explanation tab. **Not yet measured:** the rubric has not been run against the model; the next run of the command is its first test. The rubric's numbers come from the prompt's worked examples, not from data.

Still open: A1/A2 (the embedding percentage and the two 40 floors), now with cosine ranges to set them from; and whether "related" should expect the "Some overlap" band at all, since the prompt's own rule puts a different thing under 30. The labels remain a draft.

**2026-11-15, second measurement.** The first rubric was wrong and made it worse: missed duplicates went from 3 of 12 to 10 of 12, false positives stayed 0 of 24. The judge answered `same_features: partly` for nine of the twelve duplicates (a pre-proposal never lists exactly a finished study's features) and the rubric priced yes / yes / partly at 60, five under the line. The answers themselves separate well: `same_things` was yes for 10 duplicates, partly for 1, no for 1 (water refilling, whose label is doubtful), and no for 23 of 24 non-duplicates. Reweighted: same thing with some features shared is 70 (the prompt's own "an extra feature narrows the gap, score ~70" example), and users now cost 20 when they differ. Worked out by hand on that run's answers, the new weights miss 2 of 12 (water refilling 15, incident report 45) with 0 false positives. **That is a fit to the same 36 pairs it is checked on, not a measurement**; it needs a fresh run, and pairs it has not seen. The model's own number also moved between runs because the prompt changed (alumni 15 to 75), so the "3 of 12" before and after are not the same three.

**2026-11-15, third measurement.** With the reweighted rubric: score shown 0 of 24 false positives, 3 of 12 missed; the model's own number, the same. So on this set the rubric is no more accurate than the number it replaced; what it adds is that each score can be explained from three answers. The two miss different pairs (rubric: health unit 25, water refilling 15, incident report 45; own number: health unit 15, boarding house 45, water refilling 35). The hand calculation above predicted 2 of 12 and was wrong because the judge's answers for health unit changed between two runs of an identical prompt (yes / yes / partly, then partly / yes / no), as did parking-related: temperature is already 0 with top_k 1, so the judge is not repeatable on borderline pairs, possibly because `generateMany()` runs prompts in parallel. Not investigated.

The weights are now frozen. `tests/Fixtures/duplication-gold-set-2.json` holds 36 new pairs (12 new pre-proposals) that the weights were not adjusted on; run it with `php artisan duplication:evaluate --file=tests/Fixtures/duplication-gold-set-2.json`. Same caveat: labels drafted from titles, not reviewed by a person.

**2026-11-15, held-out measurement.** `duplication-gold-set-2.json`, weights untouched: score shown 0 of 24 false positives, 2 of 12 missed (enrollment with face recognition 45, birthing home 45); the model's own number 0 of 24 and 2 of 12 (venue reservation 45, class scheduling 45). Cosine by label: duplicate 0.73 to 0.89, related 0.60 to 0.74, unrelated 0.44 to 0.57. Both sets together, latest runs: 0 of 48 false positives and 5 of 24 missed duplicates by either score. The highest score any non-duplicate got in either set, by either score, is 35, against a line of 65.

Two patterns, noted and **not acted on**, since acting on them would be fitting to the set kept aside:
- Three of the rubric's five misses are `partly / yes / partly` (45): the judge says "the exact same core problem" and answers `same_things: partly` because the archived study covers more.
- The rubric and the model's own number miss different pairs. Calling a study close when either does would have missed 2 of 24 with still 0 of 48 false positives. It needs a third set to confirm before it is used.

Still open: a person's review of the 72 labels; A1/A2; the judge answering one identical prompt two ways.

**2026-11-15, A1 and A2 done.** The meaning match is now `(cosine - 0.40) / 0.50 × 100`, clamped to 0 to 100: 0 where unrelated studies measured and 100 where the same study did (`SimilarityService::COSINE_UNRELATED`, `COSINE_SAME`). On the two sets that puts unrelated at 8 to 34%, related at 24 to 72% and duplicates at 66 to 100%; it was 72 to 96% for everything. The retrieval floor is cosine 0.40 (no labelled pair of any kind measured under 0.44), and `judge_min_score` defaults to 0 so every kept candidate is still read; **a server whose `.env` sets `OLLAMA_JUDGE_MIN_SCORE=40` must remove it or set it to 0**, or candidates under cosine 0.60 go unread. Each match now saves its `cosine`; the match page shows the new arithmetic for those and the old `(cos + 1) / 2` line for checks saved before. The two constants are for nomic-embed-text with today's text building; a change to either (Phase 2 steps 1, 2 and 5) needs them measured again. This-year titles are embedded from different fields than archive rows (A6), and their cosines were never measured.
