<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * God of Prompt–style demo content (post-UI-001 upgrade):
 *
 * - Parent categories + subcategories (like GoP's Art & Design → Product
 *   Photography) so the navbar dropdown and library sidebar have depth.
 * - Every prompt carries type, recommended tools, audience, and tips —
 *   the metadata the detail page renders.
 * - Version history: several prompts ship v1 + v2 so the history tab is real.
 * - Version 2 of the demo image prompt has a seeded cover image so the
 *   image-display branch is visible.
 * - Idempotent: skips if prompts already exist.
 */
class DemoContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Prompt::query()->exists()) {
            $this->command?->warn('DemoContentSeeder skipped — prompts already exist.');

            return;
        }

        // ---------------------------------------------------------- users
        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'Aasha Gurung',
            'email' => 'admin@promptsewa.test',
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'bio' => 'Keeping the vellum clean.',
        ]);

        $creators = collect([
            ['Bibek Shrestha', 'bibek@promptsewa.test', 'Prompt engineer. 6 years shipping LLM features.'],
            ['Maya Tamang', 'maya@promptsewa.test', 'Marketing copywriter turned prompt smith.'],
            ['Dorje Lama', 'dorje@promptsewa.test', 'Illustrator exploring generative art.'],
        ])->map(fn (array $data) => User::create([
            'name' => $data[0],
            'email' => $data[1],
            'password' => $password,
            'role' => User::ROLE_CREATOR,
            'bio' => $data[2],
        ]));

        // ----------------------------------------------------- categories
        // [name, parentKey|null, icon, typeScope] — parents first so
        // subcategories can link. typeScope null = universal; image/video
        // categories are scoped so the create form only offers them for
        // that prompt type (God of Prompt keeps image gen in its own space).
        $categoryData = [
            'Writing & Content' => [null, '✍️', null],
            'Image Generation' => [null, '🎨', 'image'],
            'Video & Motion' => [null, '🎬', 'video'],
            'Coding & Development' => [null, '💻', null],
            'Marketing & Growth' => [null, '📈', null],
            'Business & Productivity' => [null, '💼', null],
            'Education & Research' => [null, '🎓', null],
            'Product Photography' => ['Image Generation', '📸', 'image'],
            'Portrait & Avatar' => ['Image Generation', '🧑', 'image'],
            'Social Media Content' => ['Marketing & Growth', '📱', null],
            'Email & Outreach' => ['Marketing & Growth', '📧', null],
            'Data & SQL' => ['Coding & Development', '🗄️', null],
        ];

        $categories = collect();

        foreach ($categoryData as $name => [$parentName, $icon, $typeScope]) {
            $category = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'icon' => $icon,
                'type_scope' => $typeScope,
                'parent_id' => $parentName !== null ? $categories->get($parentName)?->id : null,
                'position' => $categories->count(),
            ]);

            $categories->put($name, $category);
        }

        // -------------------------------------------------------- prompts
        // [category, creatorIdx, title, description, type, body, tags,
        //  tools, audience, tips, pricePaisa, versions]
        // versions: [[body, changelog, tags], ...] — first is v1.
        $library = [

            ['Writing & Content', 0, 'Cold Email That Actually Gets Replies',
                'A 4-step cold email framework that opens with a provokable claim, proves it in one line, and closes with a zero-friction ask. Tuned against 400+ real sends.',
                'text',
                "You are a senior B2B copywriter specializing in cold outreach.\n\nWrite a cold email to {{prospect_name}}, {{role}} at {{company}}, about {{pain_point}}.\n\nRules:\n1. Subject line: max 6 words, lowercase, curiosity-driven.\n2. Line 1: a specific, provokable claim about their industry (no flattery).\n3. Line 2: one sentence of proof (metric, logo, or case study placeholder).\n4. Line 3: a zero-friction ask — reply with one word.\n\nTone: peer-to-peer, confident, concise. Max 90 words total. No emojis, no \"I hope this finds you well\".",
                ['sales', 'outreach', 'email'],
                ['ChatGPT', 'Claude'], 'Sales teams',
                ['Keep the pain point specific — "replies" beats "growth".', 'Send 50 before tweaking; the claim line carries the email.'],
                29900,
                [['You are a senior B2B copywriter. Write a 3-line cold email: provokable claim, proof, one-word ask.', 'Initial release. Short version for quick sends.', ['sales', 'email']]]],

            ['Email & Outreach', 1, 'Blog Post Outline Architect',
                'Turns any topic into a research-backed, SEO-aware outline with H2/H3 structure, internal-link slots and a hook paragraph — before you write a single sentence.',
                'text',
                "You are a content strategist for {{niche}} blogs.\n\nGiven the topic \"{{topic}}\", produce:\n1. A working title (< 60 chars) with the primary keyword near the front.\n2. A 2-sentence hook paragraph that names the reader's problem.\n3. 5-7 H2 sections, each with 2-3 H3 bullets and a one-line note on what evidence or example belongs there.\n4. A \"further reading\" slot list for internal links (describe the ideal anchor page).\n5. One FAQ section with 3 questions pulled from real search intent.\n\nOutput as markdown. No preamble.",
                ['blog', 'seo', 'writing'],
                ['ChatGPT', 'Claude'], 'Content marketers',
                ['Feed it a competitor URL in {{topic}} to bias the outline against what already ranks.', 'Regenerate section 3 with \"make H3s more contrarian\" for a sharper angle.'],
                0,
                []],

            ['Writing & Content', 1, 'Story-Driven Landing Page Copy',
                'Generates a complete landing page built on a customer-story arc: problem, discovery, transformation — with headline variants and objection-handling FAQ.',
                'text',
                "Act as a conversion copywriter who uses the story-brand framework.\n\nProduct: {{product}}. Audience: {{audience}}. Primary outcome: {{outcome}}.\n\nWrite a landing page with:\n- 3 headline variants (outcome-led, problem-led, curiosity-led)\n- Subheadline (max 20 words)\n- A 3-step \"how it works\" using the customer as the hero and the product as the guide\n- One mini customer story (120 words) with a placeholder for a real quote\n- FAQ answering the top 3 objections\n\nVoice: plain, warm, zero hype words.",
                ['copywriting', 'landing-page', 'conversion'],
                ['Claude', 'ChatGPT'], 'Founders',
                ['Paste three real customer quotes into the chat after the first draft — ask it to weave one in.', 'The objection FAQ is the conversion lever; make each objection a real sales-call moment.'],
                49900,
                []],

            ['Writing & Content', 2, 'Weekly Newsletter Drafting Assistant',
                'Feeds on your raw notes and returns a publish-ready newsletter issue: one big idea, three curated links with takes, and a sign-off that sounds human.',
                'text',
                "You are the editor of a weekly newsletter for {{audience}}.\n\nI will paste raw notes and links. Produce an issue with:\n1. Subject line + preview text (A/B pair)\n2. \"The big idea\" — 150 words expanding my central note, with one concrete example\n3. Three link roundups: for each, a 2-sentence take that adds context the link alone doesn't\n4. A sign-off question to drive replies\n\nKeep my voice: {{voice_notes}}. Cut anything that sounds like a press release.",
                ['newsletter', 'writing', 'curation'],
                ['ChatGPT'], 'Newsletter writers',
                ['The voice_notes variable is everything — paste 3 of your real paragraphs, not a description of your tone.'],
                0,
                []],

            ['Product Photography', 2, 'Cinematic Product Photography Frames',
                'Midjourney-ready prompts for dramatic product shots: studio rim lighting, shallow depth of field, and surface material control. Includes 6 lighting presets.',
                'image',
                "Generate a photorealistic product photograph of {{product}}.\n\nParameters to apply:\n- Composition: product centered on {{surface}}, shot at eye level, 85mm lens, f/1.8\n- Lighting: rim light from behind-left, soft key from front-right, dark gradient background\n- Mood: cinematic, premium, understated\n- Style: --style raw --ar 4:5 --v 6\n\nVariations: repeat with (a) cool blue rim light, (b) warm tungsten key, (c) top-down flat lay with hard shadow, (d) wet surface with reflections, (e) floating product with motion blur, (f) low-angle heroic framing.",
                ['midjourney', 'product', 'photography'],
                ['Midjourney', 'DALL-E', 'Flux'], 'E-commerce sellers',
                ['Name the surface material explicitly — brushed steel and matte ceramic read completely differently.', 'For hero shots, run variation (e) floating product at --ar 16:9 for web banners.'],
                39900,
                [["Generate a photorealistic product photograph of {{product}} on {{surface}}, 85mm, f/1.8, rim light, cinematic.\n\n--style raw --ar 4:5 --v 6", 'Initial release: core single-shot prompt.', ['midjourney', 'product']]]],

            ['Image Generation', 2, 'Minimalist Vector Icon Generator',
                'Produces consistent 2px-stroke vector-style icons for any concept, in a locked visual grammar so a full set looks like one designer made it.',
                'image',
                "Create a minimalist vector icon of {{concept}}.\n\nConstraints (apply to every icon in the set):\n- Grid: 24x24, 2px uniform stroke, rounded caps and joins\n- Style: geometric, flat, no gradients, no fills except 15% accent\n- Corner radius: 4px maximum\n- Include one 45° accent detail (dot, slash, or arc)\n- Negative space: at least 30% of canvas\n\nThen list 3 rules for extending the set to new concepts so they match.",
                ['icons', 'vector', 'design'],
                ['Midjourney', 'Ideogram'], 'Product designers',
                ['Generate the full set in one conversation so the model keeps the grammar consistent.'],
                0,
                []],

            ['Portrait & Avatar', 2, 'Fantasy Book Cover Illustrator',
                'Builds layered illustration briefs for epic-fantasy covers: character pose, worldbuilding props, typography zone, and a color-script that survives print.',
                'image',
                "Illustrate a book cover concept for a {{subgenre}} novel titled \"{{title}}\".\n\nBrief must specify:\n1. Central figure: pose, costume era, emotional register, back-three-quarter view facing the typography zone\n2. Setting: one worldbuilding landmark + weather condition that mirrors the plot\n3. Color script: 3 dominant colors + 1 accent, hex codes, print-safe saturation\n4. Composition: rule-of-thirds, clear title zone top 20%, series-branding zone bottom 10%\n5. Style: painted realism, visible brushwork, cinematic key light\n\n--ar 2:3 --v 6 --style raw",
                ['illustration', 'book-cover', 'fantasy'],
                ['Midjourney', 'Flux'], 'Authors',
                ['Lock the accent color first — it should echo the series branding across a full shelf.'],
                34900,
                []],

            ['Image Generation', 1, 'Isometric Room Interior Concepts',
                'Generates cozy isometric dioramas of interiors with consistent camera angle, palette discipline, and cutaway walls — perfect for game concepts and editorial art.',
                'image',
                "Create an isometric cutaway of a {{room_type}} interior.\n\nLocked parameters:\n- Camera: true isometric (30°), top-down-left\n- Walls: cutaway on the two far sides\n- Palette: 4 colors from {{palette}}, plus warm wood neutrals\n- Props: 8-12 objects telling the story of who lives here\n- Light: single window light source, soft ambient occlusion\n- Style: 3D render, clay materials, subtle grain --ar 1:1",
                ['isometric', 'interior', '3d'],
                ['Midjourney', 'DALL-E'], 'Game designers',
                ['Decide the inhabitant\'s story before generating — the prop list carries the whole image.'],
                0,
                []],

            ['Coding & Development', 0, 'Pull Request Review Copilot',
                'Reviews a diff like a senior engineer: correctness first, then security, then style — with severity labels and suggested patches instead of vague complaints.',
                'text',
                "You are a staff engineer reviewing a pull request.\n\nI will provide a diff. Review in this exact order:\n1. CORRECTNESS: logic bugs, edge cases, error handling gaps\n2. SECURITY: injection, authz, secrets, unsafe deserialization\n3. PERFORMANCE: N+1 queries, unbounded loops, memory growth\n4. STYLE: only violations of the project's stated conventions\n\nFor each finding: [SEVERITY: blocker|major|minor] file:line — what is wrong, why it matters, and a concrete suggested patch in a code block.\n\nEnd with: \"Overall: approve / request changes\" plus a one-sentence justification. Never comment on formatting a formatter would fix.",
                ['code-review', 'github', 'engineering'],
                ['Claude', 'ChatGPT'], 'Developers',
                ['Paste the PR description in too — context halves the false positives.'],
                0,
                []],

            ['Coding & Development', 0, 'Legacy Code Refactoring Planner',
                'Takes a scary legacy file and returns a staged, zero-big-bang refactoring plan: seams to cut, tests to pin behavior first, and commit-sized steps.',
                'text',
                "You are a refactoring specialist following \"Working Effectively with Legacy Code\".\n\nI will paste a legacy module. Produce:\n1. BEHAVIOR INVENTORY: what this code actually does (not what names suggest)\n2. CHARACTERIZATION TESTS: 3-5 tests to pin current behavior before touching anything\n3. SEAM LIST: injection points to break dependencies, cheapest first\n4. STAGED PLAN: commit-sized steps, each leaving the code green, with rollback notes\n5. RISK MAP: which steps might change behavior and how to detect it\n\nNever propose a rewrite. Smallest reversible steps only.",
                ['refactoring', 'clean-code', 'legacy'],
                ['Claude'], 'Developers',
                ['Run it on one class at a time; whole modules produce generic plans.'],
                44900,
                []],

            ['Data & SQL', 1, 'SQL Query Optimizer Explainer',
                'Paste a slow query and get a line-by-line execution walkthrough, index recommendations with reasoning, and a rewritten version — explained, not just delivered.',
                'text',
                "You are a database performance engineer.\n\nI will paste a SQL query and its table sizes. Respond with:\n1. WHAT IT DOES: plain-language walkthrough of the execution order\n2. COST DRIVERS: which operations dominate (scans, sorts, spills) and why\n3. INDEX PLAN: recommended indexes with column order rationale (respecting left-prefix rule)\n4. REWRITE: an optimized query in a code block, with what changed and expected effect\n5. VERIFY: exact EXPLAIN output fragments that confirm the improvement\n\nAssume MySQL 8 / MariaDB unless told otherwise. Explain like a mentor, not an oracle.",
                ['sql', 'performance', 'database'],
                ['ChatGPT', 'Claude'], 'Backend engineers',
                ['Include real row counts — the index plan changes completely at 10M rows vs 10K.'],
                0,
                []],

            ['Social Media Content', 1, '30-Day Social Media Content Calendar',
                'Maps a month of posts across platforms with a repeatable weekly arc: teach, prove, story, sell — each with hook, format, and CTA matched to funnel stage.',
                'text',
                "You are a social media strategist for {{business}} targeting {{audience}}.\n\nBuild a 30-day calendar with a weekly arc: Monday teach, Wednesday prove (case/metric), Friday story, Sunday sell.\n\nFor each day output: platform ({{platforms}}), hook (first 5 words), format (thread/carousel/short/reel), core message, CTA, and funnel stage (TOFU/MOFU/BOFU).\n\nRules: 80% value-first, max 2 hard sells per week, every post passes the \"would I save this?\" test. Output as a markdown table.",
                ['social-media', 'calendar', 'content'],
                ['ChatGPT'], 'Social media managers',
                ['Fill platforms with what you actually post to — the funnel mapping breaks across more than 3.'],
                29900,
                []],

            ['Business & Productivity', 0, 'Meeting Notes → Action Items Extractor',
                'Turns messy meeting transcripts into owner-assigned action items with deadlines, decisions log, and open questions — in under 100 words per meeting.',
                'text',
                "You are an executive assistant with perfect recall.\n\nI will paste raw meeting notes. Extract:\n1. DECISIONS: what was decided, by whom\n2. ACTION ITEMS: table of task | owner | due date | dependency — infer owners from \"I'll...\" statements; flag [UNASSIGNED] and [NO DEADLINE] explicitly rather than guessing\n3. OPEN QUESTIONS: anything raised but unresolved\n4. ONE-LINE SUMMARY for someone who wasn't there\n\nTone: telegraphic. No interpretation beyond the transcript.",
                ['meetings', 'productivity', 'summary'],
                ['ChatGPT', 'Claude'], 'Team leads',
                ['Works best with the raw transcript, not minutes — hedging language is where owners hide.'],
                0,
                []],

            ['Business & Productivity', 1, 'Investor Update Email Composer',
                'Structures a monthly investor update: metrics-first, honest misses with mitigation, one specific ask — the format angels actually read and forward.',
                'text',
                "You are a founder writing a monthly investor update.\n\nInputs: {{metrics}}, {{wins}}, {{misses}}, {{asks}}.\n\nFormat:\n1. TL;DR: 3 bullets — headline number, biggest win, biggest miss\n2. METRICS: table with MRR/Growth/Runway vs last month and vs plan, deltas explained in one line each\n3. WINS: max 3, each tied to a metric or milestone\n4. MISSES: what happened, root cause, mitigation — never spin\n5. ASKS: one specific, forwardable request (intro, hire, insight)\n\nTone: calm, factual, founder-to-owner. Under 400 words.",
                ['investor', 'fundraising', 'email'],
                ['ChatGPT'], 'Startup founders',
                ['Never round the miss numbers — precision is what buys you patience.'],
                39900,
                []],

            ['Video & Motion', 2, 'Cinematic Travel B-Roll Sequences',
                'Generates shot-by-shot video prompts for AI video tools: camera movement, lighting window, and pacing notes that cut together into one coherent sequence.',
                'video',
                "Create a 6-shot b-roll sequence of {{location}} at {{time_of_day}}.\n\nFor each shot specify:\n- Framing (wide/medium/macro) and camera move (push, orbit, handheld drift, static)\n- Light quality and direction\n- One motion element (steam, traffic, fabric, birds) that keeps the frame alive\n- Duration 4-6 seconds\n\nConstraints: consistent color grade across shots, no cuts on static-to-static, end on a wide with negative space for a title. Output as a numbered shot list ready to paste into a video model.",
                ['video', 'b-roll', 'cinematic'],
                ['Sora', 'Runway', 'Kling'], 'Filmmakers',
                ['Generate all six shots in one prompt so the grade stays consistent across the sequence.'],
                49900,
                []],

            ['Video & Motion', 0, 'Product Demo Video Storyboarder',
                'Turns a feature list into a 30-second demo storyboard: hook, problem, product-in-use, payoff — with on-screen text suggestions and per-scene video prompts.',
                'video',
                "You are a product video director.\n\nFeature list: {{features}}. Audience: {{audience}}. Tone: {{tone}}.\n\nProduce a 30-second storyboard:\n1. HOOK (0-3s): the user's pain in one visual\n2. PROBLEM (3-8s): the workaround they endure today\n3. PRODUCT (8-22s): two scenes showing the features in real use\n4. PAYOFF (22-30s): the outcome, one emotional beat\n\nFor each scene: shot description, on-screen text (max 6 words), motion style, and a ready-to-paste video model prompt. Keep every scene under one idea.",
                ['video', 'storyboard', 'product-demo'],
                ['Sora', 'Runway'], 'Product marketers',
                ['Write the payoff line first — the whole storyboard hangs on it.'],
                0,
                []],

            ['Education & Research', 0, 'Socratic Tutor for Any Topic',
                'Never gives the answer directly: diagnoses your misconception through layered questions, then builds understanding one rung at a time. Ruthlessly patient.',
                'text',
                "You are a Socratic tutor for {{topic}}.\n\nRules of engagement:\n1. Ask ONE question at a time; wait for my answer\n2. Diagnose the misconception behind wrong answers before correcting\n3. Build from what I already know — anchor new ideas to my own examples\n4. Never state the final answer until I derive it\n5. If I'm stuck twice, simplify the question, don't give up\n6. Every 3 exchanges, summarize what I've established so far\n\nBegin by asking what I already believe about {{topic}}.",
                ['tutoring', 'learning', 'socratic'],
                ['ChatGPT', 'Claude'], 'Students',
                ['Answer honestly instead of guessing — the diagnosis is the value.'],
                0,
                []],

            ['Education & Research', 1, 'Research Paper Deconstruction Grid',
                'Feeds a paper\'s abstract and figures and returns a structured grid: claim, method, evidence, limitations, and what would falsify it — for real critical reading.',
                'text',
                "You are a research methods lecturer.\n\nI will paste a paper abstract (and results if available). Complete this grid:\n1. CLAIM: the central assertion in one sentence\n2. METHOD: design, sample, measurement — name the design class explicitly\n3. EVIDENCE: which results support which claim components\n4. LIMITATIONS: what the authors admit + what they don't\n5. FALSIFIERS: 2 findings that would invalidate the claim\n6. SO WHAT: one sentence on who should care and why\n\nRefuse to fill any cell the text doesn't support — write \"not stated\" instead of guessing.",
                ['research', 'critical-thinking', 'academia'],
                ['Claude'], 'Researchers',
                ['Paste the limitations section verbatim — models under-report them unless anchored.'],
                24900,
                []],

            ['Marketing & Growth', 1, 'Landing Page Teardown & Rewrite',
                'Destructive but constructive: critiques your landing page section by section, then rewrites the three weakest with conversion rationale for each change.',
                'text',
                "You are a conversion optimization lead.\n\nI will paste my landing page copy. Respond with:\n1. SCORECARD: clarity, credibility, urgency, differentiation — each 1-10 with one-line reasoning\n2. TEARDOWN: section-by-section — what it says, what a skim-reader actually absorbs, what's lost\n3. REWRITE: the 3 weakest sections, rewritten, with a one-line rationale per change\n4. TEST PLAN: the single highest-value A/B test to run first\n\nBe blunt. Praise wastes tokens.",
                ['conversion', 'landing-page', 'critique'],
                ['Claude', 'ChatGPT'], 'Growth marketers',
                ['Include the hero image description — visual/copy mismatch is a top-3 conversion killer.'],
                0,
                []],

            ['Business & Productivity', 0, 'Weekly Priority Operating System',
                'A Monday-morning ritual prompt: dumps your open loops into a priority matrix, protects deep-work blocks, and outputs a realistic week plan with a Friday review sheet.',
                'text',
                "You are my weekly operating system.\n\nI will dump everything on my plate. Process it:\n1. TRIAGE: sort into (a) must ship this week, (b) schedule, (c) delegate, (d) decline/drop — challenge anything ambiguous into (a) or (d)\n2. ENERGY MAP: assign hard tasks to my stated peak hours: {{peak_hours}}\n3. WEEK GRID: Mon-Fri blocks with one deep-work block per day, meetings batched\n4. FRIDAY REVIEW: 3 questions I'll answer to close the week\n\nRules: max 3 must-ship items per day. If everything is urgent, ask me which single thing makes the rest irrelevant.",
                ['productivity', 'planning', 'weekly-review'],
                ['ChatGPT', 'Claude'], 'Busy professionals',
                ['The peak_hours field is the unlock — most people protect the wrong hours.'],
                19900,
                []],

            ['Marketing & Growth', 0, 'A/B Test Hypothesis Generator',
                'Reviews your funnel step and generates ranked experiment hypotheses with expected lift, effort, and a pre-registered success metric before you build anything.',
                'text',
                "You are an experimentation lead running a CRO program.\n\nFunnel step: {{funnel_step}}. Current conversion: {{rate}}. Traffic/month: {{traffic}}.\n\nGenerate 5 experiment hypotheses, ranked by expected value:\n- HYPOTHESIS: \"We believe [change] for [audience] will [effect] because [insight]\"\n- METRIC: primary success metric + guardrail metric\n- LIFT ESTIMATE: honest range with reasoning\n- EFFORT: build cost (low/med/high)\n- RISK: what could regress\n\nKill anything that can't hit minimum detectable effect with my traffic. Order by lift × confidence ÷ effort.",
                ['ab-testing', 'cro', 'experimentation'],
                ['ChatGPT'], 'Growth teams',
                ['Give real traffic numbers — it will honestly kill tests you can\'t power.'],
                0,
                []],

            ['Email & Outreach', 1, 'Follow-up Sequence That Doesn\'t Stalk',
                'Builds a 4-touch follow-up sequence: each email adds new value instead of "just bumping this" — with exit rules that preserve the relationship.',
                'text',
                "You are an outbound specialist with a reputation to protect.\n\nContext: {{context}}. Previous email: {{previous_email}}.\n\nWrite a 4-touch follow-up sequence where:\n- Touch 2 adds a new proof point (not a reminder)\n- Touch 3 changes medium and angle (voice note script, or a one-question email)\n- Touch 4 is a graceful exit that leaves the door open\n\nEach email: max 60 words, one clear ask, subject line that references the thread. Never use \"following up\", \"circling back\", or \"bumping\".",
                ['follow-up', 'outbound', 'sequences'],
                ['ChatGPT', 'Claude'], 'Sales reps',
                ['Feed the actual previous email — generic context produces generic sequences.'],
                0,
                []],
        ];

        // Cover images: give 3 image prompts the real seeded cover, plus a
        // deterministic SVG for one more so the image-display branch shows.
        $covers = [
            'Cinematic Product Photography Frames' => 'covers/cinematic-product-frames.svg',
            'Fantasy Book Cover Illustrator' => 'covers/fantasy-book-cover.svg',
            'Cinematic Travel B-Roll Sequences' => 'covers/travel-broll.svg',
            'Isometric Room Interior Concepts' => 'covers/isometric-room.svg',
        ];

        foreach ($library as [$categoryName, $creatorIdx, $title, $description, $type, $body, $tags, $tools, $audience, $tips, $price, $versions]) {
            $prompt = Prompt::create([
                'user_id' => $creators[$creatorIdx]->id,
                'category_id' => $categories->get($categoryName)->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'description' => $description,
                'search_text' => $title.' '.$description.' '.implode(' ', $tags),
                'license_tier' => $price > 0 ? Prompt::LICENSE_COMMERCIAL : Prompt::LICENSE_PERSONAL,
                'price_cents' => $price,
                'status' => Prompt::STATUS_PUBLISHED,
                'visibility' => Prompt::VISIBILITY_PUBLIC,
                'type' => $type,
                'cover_image_path' => $covers[$title] ?? null,
            ]);

            // v1 … vN history: seeded past versions first, then the final
            // (latest) body as the newest version.
            $versionRows = [];
            foreach ($versions as $i => [$vBody, $vChangelog, $vTags]) {
                $versionRows[] = ['body' => $vBody, 'changelog' => $vChangelog, 'tags' => $vTags];
            }
            $versionRows[] = ['body' => $body, 'changelog' => $versions === [] ? 'Initial release.' : 'Expanded variations, locked lighting grammar, and tool-specific notes.', 'tags' => $tags];

            foreach ($versionRows as $i => $row) {
                PromptVersion::create([
                    'prompt_id' => $prompt->id,
                    'version_number' => $i + 1,
                    'body' => $row['body'],
                    'changelog' => $row['changelog'],
                    'tags' => $row['tags'],
                    'recommended_tools' => $tools,
                    'audience' => $audience,
                    'tips' => $tips,
                    'user_id' => $creators[$creatorIdx]->id,
                    'status' => PromptVersion::STATUS_PUBLISHED,
                ]);
            }

            if ($price > 0) {
                Product::create([
                    'prompt_id' => $prompt->id,
                    'price_paisa' => $price,
                    'status' => Product::STATUS_ACTIVE,
                    'currency' => 'NPR',
                ]);
            }
        }

        // ------------------------------------- non-public states (hidden)
        $hiddenStates = [
            [0, 'Untitled Draft — Voice Memo Summarizer', Prompt::STATUS_DRAFT, Prompt::VISIBILITY_PUBLIC, 'text'],
            [1, 'Quarterly OKR Review Facilitator', Prompt::STATUS_PENDING, Prompt::VISIBILITY_PUBLIC, 'text'],
            [2, 'Private Client Recipe — Brand Voice Cloner', Prompt::STATUS_PUBLISHED, Prompt::VISIBILITY_PRIVATE, 'text'],
            [0, 'Archived — Twitter Thread Reformatter', Prompt::STATUS_REJECTED, Prompt::VISIBILITY_PUBLIC, 'text'],
        ];

        foreach ($hiddenStates as [$creatorIdx, $title, $status, $visibility, $type]) {
            $prompt = Prompt::create([
                'user_id' => $creators[$creatorIdx]->id,
                'category_id' => $categories->get('Writing & Content')->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'description' => 'This listing is not public — used to verify visibility handling.',
                'search_text' => $title.' internal hidden listing',
                'license_tier' => Prompt::LICENSE_PERSONAL,
                'price_cents' => 0,
                'status' => $status,
                'visibility' => $visibility,
                'type' => $type,
            ]);

            PromptVersion::create([
                'prompt_id' => $prompt->id,
                'version_number' => 1,
                'body' => 'Hidden listing body — must never appear on public pages.',
                'tags' => ['internal'],
                'recommended_tools' => ['ChatGPT'],
                'user_id' => $creators[$creatorIdx]->id,
                'status' => PromptVersion::STATUS_PUBLISHED,
            ]);
        }

        $this->command?->info('Demo content seeded:');
        $this->command?->line('  Admin login:    admin@promptsewa.test / password');
        $this->command?->line('  Creator logins: bibek@ / maya@ / dorje@promptsewa.test / password');
        $this->command?->line('  Admin user id:  '.$admin->id);
    }
}
