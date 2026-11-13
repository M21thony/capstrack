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
