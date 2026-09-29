<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bulk storefront catalog: additional prompts across every category so the
 * live storefront launches at catalog scale.
 *
 * Runs independently of DemoContentSeeder so the update pipeline can seed
 * it on live servers that already have the original demo prompts.
 * Idempotent: skips when the bulk catalog is already present.
 */
class BulkCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // D4 (v1.4.5): HARD refuse in production — same rationale as
        // DemoContentSeeder (from-zero runs defeat idempotency markers).
        if (app()->environment('production')) {
            $this->command?->warn('BulkCatalogSeeder REFUSED — bulk demo catalog is never seeded in production (D4).');

            return;
        }

        // Marker prompt title unique to this seeder.
        if (Prompt::where('title', 'Product Launch Announcement Writer')->exists()) {
            $this->command?->warn('BulkCatalogSeeder skipped - bulk catalog already present.');

            return;
        }

        $creators = User::query()
            ->whereIn('email', [
                'bibek@promptsewa.test', 'maya@promptsewa.test', 'dorje@promptsewa.test',
                'bibek@promptvellum.test', 'maya@promptvellum.test', 'dorje@promptvellum.test',
            ])
            ->orderBy('id')
            ->get();
        if ($creators->isEmpty()) {
            $this->command?->warn('BulkCatalogSeeder skipped - no demo creator accounts found.');

            return;
        }

        $categories = \App\Models\Category::pluck('id', 'name');
        $topicMatrix = [
            'Writing & Content' => [
                ['Product Launch Announcement Writer', 'Announces {{product}} with a hook that leads with the customer outcome, not the feature list.', 'Write the launch announcement for {{product}} for {{audience}}. Lead with the outcome, one proof point, one CTA. Max 120 words.'],
                ['Case Study Structure Generator', 'Turns a raw customer win into a before/after/result case study with pull-quote slots.', 'Build a case study for {{customer}} using {{product}}. Sections: context, challenge, solution, quantified result. Mark two pull-quote slots.'],
                ['Technical Blog Post Skeleton', 'Outlines developer-facing posts that teach one concept end-to-end with runnable code.', 'Outline a technical post about {{concept}} for {{audience}}. Include a runnable minimal example, two gotchas, and a further-reading list.'],
                ['Podcast Episode Show Notes Writer', 'Produces timestamped show notes with key quotes and a click-worthy episode title.', 'Write show notes for an episode about {{topic}}. Timestamps, 3 key quotes, guest bio placeholder, episode title under 60 chars.'],
                ['YouTube Video Script Architect', 'Structures retention-optimized video scripts: hook, promise, payoff, next-video bridge.', 'Script a YouTube video about {{topic}} for {{audience}}. Hook in first 5 seconds, open loop by 15s, payoff at 70%, bridge to next video.'],
                ['Ebook Chapter Outliner', 'Breaks a big non-fiction topic into chapter arcs with exercises and progress checkpoints.', 'Outline a 10-chapter ebook on {{topic}}. Each chapter: promise, 3 sections, one exercise, checkpoint. No filler chapters.'],
                ['Press Release Template with Quotes', 'AP-style press release with executive and customer quote slots that journalists actually clip.', 'Write an AP-style press release announcing {{news}}. Include an executive quote and customer quote, both under 25 words. Boilerplate last.'],
                ['Whitepaper Executive Summary Condenser', 'Compresses a dense whitepaper into a one-page executive summary a CFO would finish.', 'Condense this whitepaper about {{topic}} into one page: problem, method, findings, recommendation. No jargon, numbers preserved.'],
                ['Interview Question Designer', 'Builds structured interview kits per role with scoring rubrics and follow-up probes.', 'Design an interview kit for a {{role}} hire. 5 questions, each with what-good-looks-like and one follow-up probe. No brain teasers.'],
                ['Documentation Style Guide Drafter', 'Produces a docs style guide: voice, tense, code-block conventions, and do/don\'t pairs.', 'Draft a documentation style guide for {{product}}. Cover voice, tense, second person, code-block rules, and 5 do/don\'t pairs.'],
            ],
            'Email & Outreach' => [
                ['Webinar Invitation Sequence', 'Three-email webinar invite arc: value-first invite, proof reminder, last-call with scarcity.', 'Write a 3-email webinar invite sequence for {{webinar_topic}}. Email 1 value, email 2 social proof, email 3 last call. Max 80 words each.'],
                ['Referral Request That Feels Earned', 'Asks for referrals right after a success moment, with a copy-paste forward message for the referee.', 'Draft a referral request to {{client_name}} after delivering {{result}}. Include a 2-sentence forwardable blurb they can send as-is.'],
                ['Re-engagement Campaign for Cold Lists', 'Win-back flow that offers an explicit downsell or exit, cleaning the list honestly.', 'Write a 3-email re-engagement flow for subscribers inactive 90+ days. Offer {{incentive}}, then a clean exit option. No guilt trips.'],
                ['Partnership Pitch One-Pager Email', 'Cold partnership email that leads with mutual audience value, not what you want.', 'Pitch a partnership to {{partner_company}}. Lead with their audience benefit, one concrete collaboration format, one ask. Max 100 words.'],
                ['Event Follow-up That Converts', 'Post-event email that references the specific talk moment and offers one next step.', 'Write a post-event follow-up for attendees of my talk on {{talk_topic}}. Reference a specific moment, one resource, one CTA.'],
                ['Waitlist Nurturing Sequence', 'Keeps waitlisted users warm with behind-the-scenes progress and a founding-member offer.', 'Write a 4-email waitlist sequence for {{product}}. Build progress updates, one origin story, founding-member pricing at launch.'],
                ['Customer Onboarding Email Drip', 'Day 1/3/7 onboarding emails that drive one activation action each, not feature tours.', 'Write day 1, 3, and 7 onboarding emails for {{product}}. Each drives exactly one activation action. Success metric named per email.'],
                ['Win-Back for Churned Customers', 'Churned-user email that names the likely reason for leaving and offers a targeted fix.', 'Write a win-back email for users who churned after {{weeks}} weeks. Name the likely reason, offer {{fix}}, make returning one click.'],
                ['Cold DM Template for Creators', 'Short social DM outreach that references specific work and proposes a micro-collab.', 'Write a cold DM to a {{platform}} creator in {{niche}}. Reference one specific piece of their work, propose a micro-collab. Under 50 words.'],
                ['Newsletter Sponsorship Pitch Kit', 'Media-kit email for newsletter sponsors with real engagement numbers and audience segments.', 'Write a sponsorship pitch for my {{niche}} newsletter. Audience: {{subscribers}}, open rate: {{open_rate}}. Two ad formats, pricing TBD call.'],
            ],
            'Coding & Development' => [
                ['Unit Test Scaffolder from Function Signature', 'Generates edge-case-first test scaffolds: happy path, boundaries, failures, property checks.', 'Write Pest tests for this function: {{signature}}. Cover happy path, boundaries, failure modes, and one property-based check.'],
                ['Docker Debugging Assistant', 'Diagnoses container failures layer by layer: build, runtime, networking, volumes.', 'Debug this Docker issue: {{error}}. Walk layers: Dockerfile, build cache, runtime env, networking, volumes. One fix hypothesis per layer.'],
                ['API Contract Reviewer', 'Reviews REST/GraphQL contracts for consistency, versioning, pagination, and error hygiene.', 'Review this API contract for {{resource}}. Check naming consistency, error shapes, pagination, versioning strategy, and idempotency.'],
                ['Git History Forensics Explainer', 'Explains why a bug was introduced using git blame and log context, not just who.', 'Analyze this git history around {{bug_description}}. Identify the introducing commit, the why, and the systemic prevention.'],
                ['Regex Builder with Test Cases', 'Builds regexes with matching and non-matching examples, plus a ReDoS safety note.', 'Build a regex for {{pattern}}. Provide 5 matching, 5 non-matching examples, explain each token, and flag catastrophic backtracking risk.'],
                ['Code Comment Quality Rewriter', 'Rewrites comments to explain why, not what — deletes noise comments entirely.', 'Rewrite these comments to explain intent and trade-offs, not mechanics. Delete any comment the code already says. Code: {{code}}'],
                ['Database Migration Safety Checker', 'Reviews schema migrations for lock risks, backwards compatibility, and rollback plans.', 'Review this migration for {{database}}: lock contention, backwards compatibility with old app version, rollback plan, index build strategy.'],
                ['Performance Bottleneck Triage', 'Steps through latency complaints methodically: measure, profile, hypothesize, fix, verify.', 'Triage this performance complaint: {{symptom}}. Order: measure first, profile, top-3 hypotheses with evidence needed, cheapest fix first.'],
                ['Error Message UX Rewriter', 'Rewrites cryptic errors into actionable messages with next steps and error codes.', 'Rewrite this error message: {{error}}. Include what happened, likely cause, user next step, and a searchable error code. Max 2 sentences.'],
                ['CI Pipeline Reviewer', 'Reviews CI configs for speed (caching, parallelism) and safety (secrets, pinned versions).', 'Review this CI config for {{stack}}. Flag missing caching, parallelization opportunities, secret leaks, and unpinned action versions.'],
            ],
            'Marketing & Growth' => [
                ['Positioning Statement Canvas', 'Forces the positioning canvas: for whom, unlike alternatives, we uniquely deliver what.', 'Build a positioning statement for {{product}}. Fill: target, category, key benefit, competitor frame, differentiation. One sentence each.'],
                ['Customer Persona Interview Script', 'Gives you the 10 questions that surface real buying motivations, not demographic fluff.', 'Write a customer interview script for {{product}}. 10 questions digging into last-attempted solutions, budget moments, and switching triggers.'],
                ['Google Ads Headline Battery', 'Generates 15 RSA headlines within char limits, mapped to pain, outcome, and differentiation.', 'Write 15 Google Ads headlines (30 chars max) for {{product}}. 5 pain-led, 5 outcome-led, 5 differentiator-led. No trademark terms.'],
                ['Competitor Teardown Framework', 'Structured teardown: their positioning, pricing, funnel, and the exploitable gap.', 'Teardown {{competitor}}. Map their positioning, pricing tiers, onboarding funnel, and 3 gaps {{product}} can exploit. Evidence over opinion.'],
                ['Launch Week Content Multiplier', 'Turns one launch into 10 assets: announcement, thread, demo script, FAQ, and more.', 'Turn our launch of {{product}} into 10 content assets: announcement post, X thread, demo script, 3 FAQs, email, ad copy, and 2 clips list.'],
                ['Referral Program Designer', 'Designs two-sided referral incentives with viral coefficient math and abuse guards.', 'Design a referral program for {{product}}. Two-sided incentive, K-factor estimate, fraud guardrails, and the in-product prompt moment.'],
                ['SEO Content Gap Analyzer', 'Finds the keywords competitors rank for that you ignore, grouped by funnel stage.', 'Analyze content gaps for {{domain}} vs {{competitor_domain}} in {{niche}}. Group missing topics by funnel stage and effort-to-rank.'],
                ['Pricing Page Rewrite Blueprint', 'Restructures pricing pages around jobs-to-be-done with anchor tiers and FAQ objection handling.', 'Rewrite our pricing page for {{product}}. Tier names as jobs-to-be-done, feature comparison logic, anchor placement, 4 objection FAQs.'],
                ['Cold Audience Ad Angles Generator', 'Generates 10 distinct ad angles from real customer motivations, not feature lists.', 'Generate 10 ad angles for {{product}} targeting {{cold_audience}}. Each angle = one customer motivation + proof element. No feature dumps.'],
                ['Churn Exit Survey Analyzer', 'Analyzes churn surveys into themes with the fixable vs structural split and revenue at risk.', 'Analyze these churn survey responses: {{responses}}. Theme them, split fixable vs structural, estimate revenue at risk, recommend top fix.'],
            ],
            'Business & Productivity' => [
                ['One-on-One Meeting Agenda Builder', 'Running 1:1 doc: wins, blockers, growth, feedback both ways — 25 minutes, no status theater.', 'Build a 25-minute 1:1 agenda for {{report_name}}. Blocks: their wins, blockers, growth topic, feedback for me, feedback for them. No status.'],
                ['SOP Writer from Screen Recording', 'Turns a rough process description into a numbered SOP with decision points and failure modes.', 'Write an SOP for {{process}}. Numbered steps, decision points as if/then, common failure modes, and the definition of done.'],
                ['Quarterly Goal Cascade Builder', 'Cascades company OKRs to team and personal level with leading indicators.', 'Cascade this company objective: {{objective}}. Team-level KRs for {{team}}, personal KRs, leading indicators, and a red-flag list.'],
                ['Decision Memo Template (Amazon style)', 'Six-page-memo discipline in two pages: context, options, analysis, recommendation.', 'Write a decision memo for {{decision}}. Context, 3 options with trade-offs table, quantified analysis, recommendation with reversal criteria.'],
                ['Meeting Cost Audit Calculator Prompt', 'Quantifies meeting load and identifies which recurring meetings to kill or shorten.', 'Audit my meeting load: {{meetings_list}}. Cost each at attendee rates, flag kill/shorten/async candidates, propose the new calendar.'],
                ['Weekly Team Update Composer', 'Stakeholder update in 90 seconds: shipped, shipping, blocked, needs decision.', 'Compose our weekly team update for {{stakeholders}}. Shipped, shipping, blocked (with unblock ask), decisions needed. Under 200 words.'],
                ['Freelancer Scope Creep Deflector', 'Polite contract-backed responses to scope creep, with the change-order path made easy.', 'Draft a response to this scope-creep request: {{request}}. Acknowledge, reference contract scope, offer change-order path with price framing.'],
                ['Job Description De-Biaser', 'Rewrites job posts to remove biased language, requirement padding, and crushed-list noise.', 'Rewrite this job description for {{role}}: remove biased terms, cut requirements to must-haves, add impact statement, salary band placeholder.'],
                ['Project Post-Mortem Facilitator', 'Blameless post-mortem: timeline, contributing factors, systemic fixes with owners.', 'Facilitate a post-mortem for {{incident}}. Timeline, contributing factors (no blame), 3 systemic fixes with owners and check dates.'],
                ['Invoice and Payment Chase Templates', 'Escalating-but-friendly payment chase sequence: reminder, nudge, formal notice.', 'Write 3 payment chase emails for invoice {{invoice_days}} days overdue. Friendly reminder, gentle nudge with re-attach, formal notice.'],
            ],
            'Education & Research' => [
                ['Feynman Technique Study Partner', 'Makes you explain a concept in plain words, then attacks the gaps in your explanation.', 'Be my Feynman study partner for {{concept}}. I explain it plainly; you find gaps, ask the dumbest smart question, and grade my explanation.'],
                ['Spaced Repetition Deck Generator', 'Converts study notes into atomic flashcards with cloze deletions and interleaving.', 'Convert these notes into spaced-repetition cards: {{notes}}. Atomic facts, cloze deletions where useful, 15-20 cards, mix difficulty.'],
                ['Learning Path Constructor', 'Builds a 12-week learning path with weekly projects and honest time estimates.', 'Build a 12-week learning path for {{skill}}. Weekly theme, one project, resource type (not brand), honest hours estimate, skip-if-you-know checks.'],
                ['Exam Mistake Post-Mortem Analyzer', 'Categorizes exam errors: knowledge gaps, misreads, time pressure — with a targeted drill plan.', 'Analyze these exam errors: {{errors}}. Categorize: knowledge gap, misread, time pressure, careless. Drill plan for the top category.'],
                ['Debate Argument Steelmanner', 'Steelmans both sides of a question, then identifies the crux that decides it.', 'Steelman both sides of {{debate_question}}. Strongest 3 arguments each, then name the crux and what evidence would settle it.'],
                ['Reading Comprehension Coach', 'Asks layered questions on any text: recall, inference, evaluation, application.', 'Coach me through this text: {{text}}. Ask one question per level: recall, inference, evaluation, application. Wait for my answers.'],
                ['Study Schedule Optimizer', 'Builds a realistic study schedule around your life, with catch-up buffers built in.', 'Optimize my study schedule for {{exam_date}}. Available hours: {{hours}}. Interleave subjects, spaced review, one full rest evening.'],
                ['Concept Analogy Generator', 'Explains hard concepts through 3 analogies at different levels, then where each breaks.', 'Explain {{concept}} via 3 analogies: everyday-life, professional-domain, and mechanical. Then state where each analogy breaks down.'],
                ['Research Question Narrower', 'Takes a broad research interest and drills to an answerable, scoped research question.', 'Narrow my research interest "{{broad_interest}}" into 5 answerable questions. Rate each on feasibility, novelty, and data availability.'],
                ['Language Learning Immersion Script', 'Generates graded-immersion dialogues at your level with glossed vocabulary.', 'Write a graded dialogue in {{language}} at {{level}} level about {{scenario}}. Gloss new vocabulary inline, 3 comprehension questions after.'],
            ],
            'Data & SQL' => [
                ['Dashboard Metric Definition Auditor', 'Audits dashboard metrics for hidden double-counting, timezone drift, and null traps.', 'Audit these dashboard metrics: {{metrics}}. Flag double-counting, timezone drift, null handling, and the denominator nobody defined.'],
                ['A/B Test Results Interpreter', 'Reads experiment results honestly: significance, segments, novelty effects, and what to decide.', 'Interpret this A/B test: {{results}}. Check significance honestly, segment effects, novelty risk, and state the decision the data supports.'],
                ['CSV Cleaning Pipeline Designer', 'Designs a repeatable cleaning pipeline: schema inference, nulls, dedupe, validation gates.', 'Design a cleaning pipeline for this CSV: {{sample_rows}}. Schema inference, null strategy, dedupe keys, validation gates that block bad rows.'],
                ['Cohort Retention Query Writer', 'Writes cohort retention SQL with clear window logic and a sanity-check row count.', 'Write a weekly cohort retention query for {{table_description}}. Clear window logic, first-touch attribution, sanity-check totals inline.'],
                ['Data Dictionary Generator from Schema', 'Generates a human data dictionary with PII flags and freshness expectations per table.', 'Generate a data dictionary for this schema: {{schema}}. Human-readable purpose per column, PII flags, likely freshness, common misuse.'],
                ['Forecast Assumption Challenged', 'Stress-tests a forecast: which assumptions are load-bearing and what breaks first.', 'Challenge this forecast: {{forecast}}. Identify load-bearing assumptions, sensitivity ranking, and the base-rate check that undermines it.'],
                ['Survey Data Weighting Explainer', 'Explains when survey data needs weighting and computes a simple reweighting plan.', 'Assess weighting needs for this survey: {{sample_vs_population}}. Compute adjustment factors, flag variance inflation, recommend reporting caveats.'],
                ['ETL Failure Post-Mortem Writer', 'Documents data pipeline failures: silent corrupt vs loud fail, and the monitoring gap.', 'Post-mortem this ETL failure: {{failure}}. Silent-corrupt vs loud-fail analysis, monitoring gap, and 2 defensive checks to add.'],
                ['Metric Tree Builder', 'Builds a driver tree from north-star metric down to movable input metrics.', 'Build a metric tree for north-star {{north_star}}. Three driver levels, input metrics we directly control, and the one metric to move first.'],
                ['Spreadsheet Formula Explainer & Fixer', 'Explains what a gnarly spreadsheet formula actually does, then rewrites it maintainably.', 'Explain this formula plainly: {{formula}}. What it really computes, edge cases it gets wrong, and a maintainable rewrite.'],
            ],
            'Social Media Content' => [
                ['X Thread Architect with Hooks', 'Builds threads where every tweet earns the swipe: hook, beats, cliffhangers, CTA.', 'Write an X thread about {{topic}} for {{audience}}. Hook tweet under 100 chars, one idea per tweet, cliffhanger beats, soft CTA at end.'],
                ['LinkedIn Carousel Storyboarder', 'Storyboards 8-slide carousels: contrarian hook slide, value slides, CTA slide.', 'Storyboard a LinkedIn carousel on {{topic}}. Slide 1 contrarian hook, slides 2-7 one insight each, slide 8 CTA. Write the actual copy.'],
                ['Instagram Caption + Hashtag System', 'Captions with the first-line hook, line breaks for readability, and a niche-stacked hashtag set.', 'Write 5 Instagram captions for {{post_theme}}. First-line hook each, 3-line max body, CTA, plus a 12-hashtag niche-stacked set.'],
                ['Short-Form Video Hook Library', 'Generates 20 scroll-stopping first-3-second hooks for your niche, categorized.', 'Generate 20 short-video hooks for {{niche}}. Categorize: contrarian, curiosity, result-first, mistake-callout, and listicle. Max 12 words each.'],
                ['Community Engagement Prompt Bank', '30 conversation-starter posts that get real replies, not emoji reactions.', 'Write 30 community engagement posts for {{community_niche}}. Mix hot-takes, this-or-that, show-your-work, and help-me-decide. No emoji-bait.'],
                ['Content Repurposing Matrix', 'Turns one pillar piece into a week of platform-native derivatives.', 'Repurpose my pillar piece on {{pillar_topic}} into: 3 tweets, 1 LinkedIn post, 1 carousel outline, 2 short-video scripts, 1 newsletter blurb.'],
                ['Influencer Collaboration Brief Writer', 'One-page creator brief: deliverables, must-say, must-not-say, approval flow.', 'Write a creator brief for {{campaign}}. Deliverables, key message, 3 must-says, 3 must-not-says, usage rights, approval timeline.'],
                ['Social Proof Collector Templates', 'DM and comment templates that turn happy customers into usable testimonials.', 'Write templates for collecting testimonials from {{customer_type}}: post-purchase DM, milestone email, and a comment reply that invites sharing.'],
                ['Trend-Jacking Safety Filter', 'Evaluates a trending format against brand risk before you jump on it.', 'Evaluate this trend for {{brand}}: {{trend}}. Audience fit, brand-risk check, effort estimate, and a go/no-go with a version of the idea.'],
                ['Monthly Analytics Recap Post', 'Transparency post: real numbers, real lessons, next month bets — the posts that build trust.', 'Write a transparency recap for {{month}}. Real metrics: {{metrics}}. What worked, what flopped, next month bets. Numbers unrounded.'],
            ],
        ];

        $priceByIndex = fn (int $i): int => [0, 19900, 24900, 29900, 34900, 39900, 44900, 49900][$i % 8];
        $typesByCategory = [
            'Product Photography' => 'image',
            'Image Generation' => 'image',
            'Portrait & Avatar' => 'image',
            'Video & Motion' => 'video',
        ];
        $bulkCount = 0;

        foreach ($topicMatrix as $categoryName => $templates) {
            $categoryId = $categories->get($categoryName);
            if ($categoryId === null) { $this->command?->warn("category missing: {$categoryName}"); continue; }
            foreach ($templates as $i => [$title, $description, $body]) {
                $type = $typesByCategory[$categoryName] ?? 'text';
                $price = $priceByIndex($bulkCount);
                $tags = [Str::slug($categoryName), strtolower(explode(' ', $title)[0])];
                $slug = Str::slug($title);

                // Unique slug per creator rotation.
                $creatorIdx = $bulkCount % 3;
                $prompt = Prompt::create([
                    'user_id' => $creators[$creatorIdx]->id,
                    'category_id' => $categoryId,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'search_text' => $title.' '.$description.' '.implode(' ', $tags),
                    'license_tier' => $price > 0 ? Prompt::LICENSE_COMMERCIAL : Prompt::LICENSE_PERSONAL,
                    'price_cents' => $price,
                    'status' => Prompt::STATUS_PUBLISHED,
                    'visibility' => Prompt::VISIBILITY_PUBLIC,
                    'type' => $type,
                ]);

                PromptVersion::create([
                    'prompt_id' => $prompt->id,
                    'version_number' => 1,
                    'body' => $body,
                    'changelog' => 'Initial release.',
                    'tags' => $tags,
                    'recommended_tools' => $type === 'image' ? ['Midjourney', 'Flux'] : ($type === 'video' ? ['Sora', 'Runway'] : ['ChatGPT', 'Claude']),
                    'audience' => 'Professionals in '.$categoryName,
                    'tips' => ['Fill every {{variable}} with specifics — generic inputs produce generic output.'],
                    'user_id' => $creators[$creatorIdx]->id,
                    'status' => PromptVersion::STATUS_PUBLISHED,
                ]);

                if ($price > 0) {
                    Product::create([
                        'prompt_id' => $prompt->id,
                        'price_paisa' => $price,
                        'status' => Product::STATUS_ACTIVE,
                        'currency' => 'NPR',
                    ]);
                }

                $bulkCount++;
            }
        }

        // Second pass: advanced/pro editions of every template so the catalog
        // reaches launch scale (200+ total). Each variant adds a quality-rubric
        // block to the body and rotates pricing/creators.
        $editionPasses = [
            ['Advanced ', 'Advanced edition: adds evaluation criteria, failure-mode analysis, and a quality rubric to iterate against.', 39900],
            ['Pro ', 'Pro edition: chains multiple model calls, adds output scoring, and includes a retuning checklist.', 49900],
        ];

        foreach ($editionPasses as [$prefix, $editionBlurb, $editionPrice]) {
            foreach ($topicMatrix as $categoryName => $templates) {
                $categoryId = $categories->get($categoryName);
                if ($categoryId === null) {
                    continue;
                }
                $type = $typesByCategory[$categoryName] ?? 'text';

                foreach ($templates as $i => [$title, $description, $body]) {
                    $creatorIdx = $bulkCount % 3;
                    $prompt = Prompt::create([
                        'user_id' => $creators[$creatorIdx]->id,
                        'category_id' => $categoryId,
                        'title' => $prefix.$title,
                        'slug' => Str::slug($prefix.$title).'-'.($bulkCount + 1),
                        'description' => $description.' '.$editionBlurb,
                        'search_text' => $prefix.$title.' '.$description.' '.$editionBlurb,
                        'license_tier' => Prompt::LICENSE_COMMERCIAL,
                        'price_cents' => $editionPrice,
                        'status' => Prompt::STATUS_PUBLISHED,
                        'visibility' => Prompt::VISIBILITY_PUBLIC,
                        'type' => $type,
                    ]);

                    PromptVersion::create([
                        'prompt_id' => $prompt->id,
                        'version_number' => 1,
                        'body' => $body."\n\nQUALITY PASS: score your own output 1-10 against the task goal, list what a 10 would add, and produce the improved version.",
                        'changelog' => $editionBlurb,
                        'tags' => [Str::slug($categoryName), 'advanced'],
                        'recommended_tools' => $type === 'image' ? ['Midjourney', 'Flux'] : ($type === 'video' ? ['Sora', 'Runway'] : ['ChatGPT', 'Claude']),
                        'audience' => 'Experienced professionals in '.$categoryName,
                        'tips' => ['Run the base edition first - the advanced pass builds on its output structure.'],
                        'user_id' => $creators[$creatorIdx]->id,
                        'status' => PromptVersion::STATUS_PUBLISHED,
                    ]);

                    Product::create([
                        'prompt_id' => $prompt->id,
                        'price_paisa' => $editionPrice,
                        'status' => Product::STATUS_ACTIVE,
                        'currency' => 'NPR',
                    ]);

                    $bulkCount++;
                }
            }
        }

        // NOTE: verification is deliberately NOT auto-granted here. Only the
        // admin and the flagship JustShipItAI account carry the badge (seeded
        // in their own seeders); everyone else is issued by an admin from the
        // Users panel.

        $this->command?->info("Bulk catalog seeded: {$bulkCount} additional prompts.");
    }
}