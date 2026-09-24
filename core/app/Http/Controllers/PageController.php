<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * Simple public pages driven by admin-editable settings.
 */
class PageController extends Controller
{
    public function about()
    {
        return view('pages.about', [
            'aboutBody' => (string) app(\App\Services\SettingsService::class)
                ->get('about_page', $this->defaultAbout()),
        ]);
    }

    private function defaultAbout(): string
    {
        return <<<'MD'
        ## What is PromptSewa?

        PromptSewa is a premium library and marketplace for AI prompts —
        discover, test, buy and sell prompts tuned for ChatGPT, Claude,
        Gemini, Midjourney and more.

        ### For buyers

        Every listing shows the full variable structure before you pay, and
        buying unlocks the complete prompt, every future version, and the
        creator's usage tips forever.

        ### For creators

        Version your prompts like git, price them in NPR, and earn from
        every sale. Submissions go through a quick review so buyers get a
        catalog they can trust.
        MD;
    }
}
