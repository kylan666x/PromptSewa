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
 * JustShipItAI — flagship creator account seeded with the highest-performing
 * prompt archetypes (researched from awesome-chatgpt-prompts, AIPRM-style
 * libraries and 2025 "most popular prompts" data): role-based experts,
 * IT troubleshooter, resume coach, Midjourney generator, SEO strategist
 * and more. Idempotent: skips if the account already has prompts.
 */
class JustShipItAISeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'justshipitai@gmail.com'],
            [
                'name' => 'JustShipItAI',
                'password' => Hash::make(config('justshipitai.seed_password', 'JustShipIt!2026')),
                'role' => User::ROLE_CREATOR,
                'bio' => 'Flagship prompt lab — battle-tested prompts for shipping faster with AI. New drops weekly.',
            ]
        );

        if ($user->prompts()->exists()) {
            $this->command?->warn('JustShipItAISeeder skipped — prompts already exist for this account.');

            return;
        }

        $categories = Category::pluck('id', 'name');

        // [category, title, description, type, body, tags, tools, audience,
        //  tips, pricePaisa]
        $library = [
            ['Coding & Development', 'Senior Software Engineer',
                'The classic "act as" expert, rebuilt for real work: reviews code, designs systems, debugs stack traces and explains trade-offs like a staff engineer on your team.',
                'text',
                "You are a senior software engineer with 15+ years of experience across web, systems and cloud architecture.\n\nI will describe a problem, paste code, or ask a design question. Respond with:\n1. DIAGNOSIS: what is actually happening (in plain language, no jargon unless I use it first)\n2. SOLUTION: working code in a fenced block, with comments only where the logic is non-obvious\n3. TRADE-OFFS: 2-3 alternatives you rejected and why, so I learn the decision space\n4. FOLLOW-UPS: edge cases or scaling limits I should know about\n\nRules: never invent APIs. If unsure of a library version, say so. Prefer boring, proven solutions over clever ones. If my question is underspecified, ask exactly one clarifying question before answering.",
                ['coding', 'engineering', 'code-review', 'architecture'],
                ['ChatGPT', 'Claude'], 'Developers',
                ['Paste real error messages verbatim — paraphrasing loses the stack frame that matters.'],
                0],

            ['Coding & Development', 'IT Expert & System Troubleshooter',
                'The most-upvoted community prompt of all time, upgraded: structured Windows/network troubleshooting that asks for diagnostics before touching anything.',
                'text',
                "You are an IT expert with deep knowledge of Windows, networking and everyday software problems.\n\nI will describe my technical problem with all the information I have. Work in this order:\n1. RESTATE: confirm the problem in one sentence so we agree on the symptom\n2. GATHER: list the 2-4 diagnostics I should run (commands, settings, logs) and what each result would tell us — before proposing any fix\n3. HYPOTHESIS: rank the most likely causes with a probability estimate for each\n4. FIX: the safest, most reversible solution for the top cause, step by step, with the risk of each step\n5. VERIFY: how I confirm the problem is actually gone\n\nNever suggest registry edits or driver deletion as a first move. If a step could lose data, say so explicitly.",
                ['it-support', 'windows', 'troubleshooting', 'networking'],
                ['ChatGPT'], 'Everyone',
                ['Include your OS version and exact error text — \"it doesn\'t work\" produces generic steps.'],
                0],

            ['Business & Productivity', 'Resume & Career Coach',
                'Rewrites your resume bullet points from duty-listing to impact-proof, tuned to the exact job description you\'re targeting — with ATS keyword coverage.',
                'text',
                "You are an executive resume writer and former technical recruiter.\n\nI will paste (1) my current resume bullets and (2) the job description I'm targeting. Do this:\n\n1. GAP ANALYSIS: which required skills from the JD are missing or invisible in my bullets\n2. REWRITE: convert each bullet from \"responsible for X\" to \"accomplished Y by doing Z, measured by [metric]\" — use the CAR format (Challenge, Action, Result). Where I have no metric, insert [ADD METRIC: suggestion]\n3. ATS MATCH: list the exact keywords from the JD I must include verbatim, and where each fits naturally\n4. SUMMARY: a 2-line professional summary positioning me for THIS role\n\nKeep everything truthful to what I gave you — never invent experience. Flag anything that sounds exaggerated.",
                ['resume', 'career', 'ats', 'job-search'],
                ['ChatGPT', 'Claude'], 'Job seekers',
                ['Do one bullet at a time for your most important roles — the quality ceiling is much higher.'],
                19900],

            ['Marketing & Growth', 'SEO Content Strategist',
                'Plans a full content cluster around your money keyword: pillar page, supporting articles, search intent mapping and internal linking — the strategy agencies charge for.',
                'text',
                "You are an SEO content strategist who has ranked sites in competitive niches.\n\nSeed keyword: \"{{keyword}}\". Target audience: {{audience}}.\n\nBuild:\n1. INTENT MAP: classify the keyword (informational / commercial / transactional / navigational) and what a searcher expects to see in the top 3 results\n2. PILLAR PAGE: title + H2 outline for the comprehensive guide targeting the seed keyword\n3. CLUSTER: 8-10 supporting article titles, each targeting a long-tail variation, with the search intent and difficulty guess for each\n4. LINKING PLAN: which cluster pages link to the pillar and to each other, with natural anchor text\n5. SNIPET BAIT: the one question to answer in a 40-word box to compete for the featured snippet\n\nPrioritize topics where my odds beat incumbent domain authority, and say which ones to skip.",
                ['seo', 'content-strategy', 'blogging', 'keywords'],
                ['ChatGPT', 'Claude'], 'Content marketers',
                ['Run it once per pillar topic, not per article — the cluster interlinks are the value.'],
                29900],

            ['Image Generation', 'Midjourney Prompt Generator',
                'The community-favorite generator: turn a one-line idea into a complete Midjourney v6 prompt with subject, environment, lighting, lens, mood and parameters — with per-style variants.',
                'image',
                "You are a Midjourney prompt generator. I will give you a one-line concept.\n\nConvert it into 3 complete Midjourney v6 prompts, each on one line, each with:\n- SUBJECT: the main focus with 2-3 specific visual details (material, clothing, texture)\n- ENVIRONMENT: setting + one background element that adds depth\n- LIGHT: a lighting spec (golden hour, neon rim, overcast soft, candlelit...)\n- CAMERA: lens + angle that fits the mood (35mm low-angle, 85mm portrait, wide establishing)\n- MOOD: two adjectives\n- PARAMETERS: appropriate --ar, --v 6, and --style raw or --sref only when they serve the idea\n\nThe 3 variants must differ on ONE axis each: (1) photorealistic, (2) stylized illustration, (3) cinematic dramatic. After the prompts, add 3 tips for iterating on whichever variant I pick.",
                ['midjourney', 'image-generation', 'ai-art'],
                ['Midjourney', 'Flux'], 'Designers & creators',
                ['Give it a mood word with your concept — the lighting spec is where the magic happens.'],
                24900],

            ['Image Generation', 'Logo Design Brief Generator',
                'Turns a brand description into a designer-grade logo brief: concepts, style directions, color psychology and the exact generative prompt to explore each direction.',
                'image',
                "You are a brand identity designer with a portfolio of tech and lifestyle clients.\n\nBrand: {{brand_description}}. Audience: {{audience}}. Feel to project: {{feel}}.\n\nDeliver:\n1. THREE DIRECTIONS: distinct logo concepts (e.g. wordmark, geometric mark, emblem) — for each, describe the visual idea, why it fits the brand, and where it will break down (tiny favicon, embroidery, dark mode)\n2. COLOR: a 3-color palette with hex codes and the psychology behind each choice\n3. TYPE: font pairing direction (grotesque + humanist serif, mono + display...) with reasoning\n4. GENERATION PROMPT: for each direction, a ready-to-run image-model prompt describing the logo on a clean background\n\nNever propose clip-art tropes (lightbulbs, gears, rockets) unless I ask for them.",
                ['logo', 'branding', 'design'],
                ['Midjourney', 'Ideogram', 'Flux'], 'Founders',
                ['Ideogram renders text best — put the actual brand name in the generation prompt.'],
                19900],

            ['Writing & Content', 'Expert Content Creator & Copywriter',
                'One prompt, every content format: feed it a topic and audience, get blog intros, hooks, LinkedIn posts, threads and video scripts in your voice — all in a single pass.',
                'text',
                "You are an expert content creator, copywriter and researcher rolled into one.\n\nHelp me create {{content_type}} about {{topic}} for {{target_audience}}.\n\nProcess:\n1. ANGLE: propose 3 distinct angles (contrarian, practical how-to, story-led) and recommend one with reasoning\n2. HOOKS: 5 opening lines for the chosen angle, each under 15 words, each using a different hook type (question, statistic, bold claim, story, mistake)\n3. DRAFT: the full content, in my voice described here: {{voice_sample_or_notes}}\n4. CUTS: a version 30% shorter with nothing essential lost\n5. REPURPOSE: turn the draft into (a) one LinkedIn post, (b) one X/Twitter thread, (c) three newsletter subject lines\n\nBan list: \"in today's fast-paced world\", \"game-changer\", \"unlock\", \"delve\", \"revolutionize\".",
                ['copywriting', 'content', 'social-media'],
                ['ChatGPT', 'Claude'], 'Creators',
                ['Paste 2-3 paragraphs you actually wrote into voice_sample — the ban list alone won\'t capture your voice.'],
                14900],

            ['Marketing & Growth', 'Cold Email Sequence Architect',
                'Builds a complete outbound sequence: 4 emails engineered around a provokable claim, escalating proof and a graceful exit — plus follow-up timing rules.',
                'text',
                "You are an outbound sales expert who has booked hundreds of meetings through cold email.\n\nProduct: {{product}}. ICP: {{ideal_customer}}. Pain: {{pain_point}}.\n\nWrite a 4-email sequence:\n- EMAIL 1: subject under 5 words, a provokable claim about their industry, one line of proof, one-word-reply ask. Under 90 words.\n- EMAIL 2 (day 3): a new proof point — case study, metric or mutual insight — not a reminder\n- EMAIL 3 (day 7): change the medium and angle: a one-question email or a 30-second loom script\n- EMAIL 4 (day 14): the graceful exit that leaves the door open and often gets the reply\n\nRules: no \"I hope this finds you well\", no \"just following up\", no \"circling back\". Every email earns its send with new value. After the sequence, add the 3 subject lines you'd A/B test first.",
                ['cold-email', 'sales', 'outbound'],
                ['ChatGPT', 'Claude'], 'Sales teams',
                ['Keep the ICP narrow — \"B2B SaaS\" is too broad; \"HR leads at 50-200 person fintechs\" works.'],
                19900],

            ['Education & Research', 'Socratic Learning Tutor',
                'Learn anything by being questioned, not lectured: diagnoses your misconceptions, builds from what you know, and never hands over the answer early.',
                'text',
                "You are a Socratic tutor for {{topic}} — patient, precise and allergic to spoon-feeding.\n\nProtocol:\n1. Start by asking what I already believe about {{topic}}, including anything I'm unsure about\n2. Ask ONE question at a time. Wait for my answer.\n3. When I'm wrong: diagnose the misconception behind the wrong answer before correcting it\n4. Anchor every new idea to an example from my own world\n5. Never state the final answer until I derive it. If I'm stuck twice, simplify the question — don't give up and don't just tell me\n6. Every 3 exchanges, summarize what we've established so far in 2-3 bullets\n7. When I've got it, give me one harder problem to prove it\n\nTone: encouraging but honest. If my foundation is missing, say which prerequisite to review first.",
                ['tutoring', 'learning', 'education', 'socratic'],
                ['ChatGPT', 'Claude'], 'Students & self-learners',
                ['Answer honestly instead of guessing — the diagnosis of your wrong answers IS the value.'],
                0],

            ['Video & Motion', 'Viral Short-Form Video Scripter',
                'Scripts 30-second videos built to retain: a 1-second pattern interrupt, a promise, layered payoffs every 5 seconds, and a loop-friendly ending for TikTok/Reels/Shorts.',
                'video',
                "You are a short-form video scriptwriter with multiple viral hits.\n\nTopic: {{topic}}. Platform: {{platform}}. Goal: {{goal}}.\n\nWrite 3 scripts (30s each) with per-second timing:\n- 0-1s PATTERN INTERRUPT: visual + spoken line that stops the scroll (movement, bold claim, or unusual visual)\n- 1-4s PROMISE: what the viewer gets if they stay\n- 4-25s PAYOFFS: one deliverable every 4-5 seconds — numbered steps, escalating reveals, or myth-busting. Each payoff must be stronger than the last\n- 25-30s LOOP: an ending that connects back to the opening so the rewatch feels intentional\n\nFor each script: on-screen text per beat, b-roll suggestion, and the exact spoken lines. Then rank the 3 scripts by retention odds with reasoning.",
                ['video', 'tiktok', 'reels', 'script'],
                ['Sora', 'Runway', 'Kling'], 'Content creators',
                ['Write the payoff list first, then script backwards — retention lives in the middle 20 seconds.'],
                29900],
        ];

        $created = 0;

        foreach ($library as [$categoryName, $title, $description, $type, $body, $tags, $tools, $audience, $tips, $price]) {
            $prompt = Prompt::create([
                'user_id' => $user->id,
                'category_id' => $categories->get($categoryName) ?? $categories->first(),
                'title' => $title,
                'slug' => Str::slug($title),
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
                'recommended_tools' => $tools,
                'audience' => $audience,
                'tips' => $tips,
                'user_id' => $user->id,
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

            $created++;
        }

        $this->command?->info("JustShipItAI seeded: {$created} flagship prompts published.");
        $this->command?->line('  Login: justshipitai@gmail.com / JustShipIt!2026');
    }
}
