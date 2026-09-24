<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromptFormRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

/**
 * Creator "Add Prompt" flow — the God of Prompt lesson applied: choosing a
 * prompt type rewrites the entire form context (body placeholder, example
 * tools, guidance) so a creator writing an image prompt is never staring
 * at a text-prompt form. Categories shown are type-aware; tools are
 * type-specific radio chips; saving derives status/price/license/search_text
 * on the server — never from the client.
 */
class PromptFormController extends Controller
{
    /** Prompt types with their form context (client JS mirrors this shape). */
    public const TYPE_CONTEXTS = [
        Prompt::TYPE_TEXT => [
            'label' => 'Text prompt',
            'icon' => '✦',
            'blurb' => 'Chains of instruction for chat models.',
            'guidance' => "Describe the model's role, the task, constraints, then the input format. Good text prompts read like a detailed brief to a new team member.",
            'body_placeholder' => "You are a senior conversion copywriter...\n\nContext: {{product_details}}\nAudience: {{target_audience}}\n\nTask: Write a landing page hero (headline under 10 words) and 3 supporting bullets.\n\nConstraints:\n- Output plain text only, no markdown\n- Tone: confident, specific, no hype words\n- End with one clear call to action",
            'example_tools' => ['ChatGPT', 'Claude', 'Gemini'],
            'audience_placeholder' => 'e.g. SaaS founders, freelance designers',
        ],
        Prompt::TYPE_IMAGE => [
            'label' => 'Image prompt',
            'icon' => '✦',
            'blurb' => 'Structured prompts for image models.',
            'guidance' => 'Build the scene layer by layer: subject, style, lighting, lens, mood. Separate ideas with commas so models can parse them.',
            'body_placeholder' => "Cinematic product photography of {{product}}\n\nScene: {{scene_description}}\nLighting: soft window light, gentle rim highlight\nCamera: 85mm, f/2.8, shallow depth of field\nStyle: photorealistic, muted color grade\nAvoid: text, watermarks, distorted labels",
            'example_tools' => ['Midjourney', 'DALL·E 3', 'Stable Diffusion'],
            'audience_placeholder' => 'e.g. e-commerce sellers, social media managers',
        ],
        Prompt::TYPE_VIDEO => [
            'label' => 'Video prompt',
            'icon' => '✦',
            'blurb' => 'Camera + motion direction for video models.',
            'guidance' => 'Direct the shot: subject action first, then camera movement, pacing, transitions. State shot length if the tool supports it.',
            'body_placeholder' => "A slow dolly-in on {{subject}}\n\nAction: {{what_happens}}\nCamera: slow dolly-in, 35mm anamorphic\nPacing: single continuous take, about 8 seconds\nMood: {{mood}}, volumetric haze\nAudio: none (silent render)\nAvoid: jump cuts, warping hands",
            'example_tools' => ['Runway Gen-3', 'Sora', 'Kling'],
            'audience_placeholder' => 'e.g. short-form creators, ad teams',
        ],
    ];

    public function create()
    {
        // All active categories ship to the client; as the creator switches
        // prompt type, JS filters out categories whose type_scope excludes
        // it (universal categories have type_scope = null).
        return view('dashboard.prompts.create', [
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name', 'type_scope']),
            'typeContexts' => self::TYPE_CONTEXTS,
        ]);
    }

    public function store(PromptFormRequest $request)
    {
        $validated = $request->fields();
        $user = $request->user();

        // All new listings enter the review queue — the founder (admin)
        // publishes them. Free vs paid changes money wiring, not moderation.
        $prompt = DB::transaction(function () use ($validated, $user, $request) {
            $prompt = Prompt::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => PromptFormRequest::uniqueSlug($validated['title']),
                'description' => $validated['description'],
                'type' => $validated['type'],
                'visibility' => $validated['visibility'],
                'search_text' => $request->searchText(),
                'license_tier' => $validated['license_tier'],
                'price_cents' => $validated['price_cents'],
                'status' => Prompt::STATUS_PENDING,
            ]);

            $prompt->versions()->create([
                'version_number' => 1,
                'body' => $validated['body'],
                'tags' => $validated['tags'],
                'recommended_tools' => $validated['recommended_tools'],
                'audience' => $validated['audience'],
                'tips' => $validated['tips'],
                'changelog' => 'Initial version',
                'user_id' => $user->id,
            ]);

            // Paid listings get their NPR product (MKT-001 integration).
            if ($validated['price_cents'] > 0) {
                Product::create([
                    'prompt_id' => $prompt->id,
                    'price_paisa' => $validated['price_cents'],
                    'currency' => 'NPR',
                    'status' => Product::STATUS_ACTIVE,
                ]);
            }

            return $prompt;
        });

        return redirect()
            ->route('dashboard')
            ->with('success', '"'.$validated['title'].'" submitted for review — approved prompts appear in the library.');
    }
}
