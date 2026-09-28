import './bootstrap';

import Alpine from 'alpinejs';

// Alpine.js gives SPA-like interactivity with zero build complexity —
// ideal for cPanel deployment where compiled assets are simply uploaded.
window.Alpine = Alpine;

/**
 * Creator "Add/Edit Prompt" form state (God of Prompt pattern).
 *
 * Choosing a prompt type rewrites the form context: body placeholder,
 * guidance copy, suggested tools, and the selectable categories
 * (type_scope'd categories only appear for their type). Typed content is
 * never wiped when switching types — only context and defaults change.
 * The server re-validates everything; this is UX, not security.
 */
/**
 * Prompt detail page viewer: copy-to-clipboard with success toast and a
 * live "fill in the variables" renderer — the marketplace's signature
 * interaction. Typed variable values instantly re-render the preview;
 * copying always copies the rendered text.
 */
/**
 * Card-level copy: gallery cards expose the raw prompt body with a one-click
 * copy button (PromptPlum pattern — the user sees the prompt before clicking
 * through). Free prompts only; paid cards deep-link to the detail page.
 */
/**
 * Navbar typeahead (TASK 2): debounced fetch to /search/preview renders a
 * Prompts/Creators dropdown under the search box. Enter submits the normal
 * full-page form (the input lives inside it, so no special handling).
 * Escape or clicking outside closes the dropdown.
 */
Alpine.data('searchPreview', (previewUrl) => ({
    q: '',
    open: false,
    loading: false,
    prompts: [],
    creators: [],
    activeIndex: -1,
    controller: null,

    get items() {
        return [
            ...this.prompts.map((p) => ({ ...p, kind: 'prompt' })),
            ...this.creators.map((c) => ({ ...c, kind: 'creator' })),
        ];
    },

    onInput() {
        const term = this.q.trim();
        this.activeIndex = -1;

        if (term.length < 2) {
            this.close();
            return;
        }

        this.loading = true;
        this.controller?.abort();
        this.controller = new AbortController();

        fetch(`${previewUrl}?q=${encodeURIComponent(term)}`, {
            signal: this.controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : { prompts: [], creators: [] }))
            .then((data) => {
                this.prompts = data.prompts || [];
                this.creators = data.creators || [];
                this.open = this.items.length > 0;
                this.loading = false;
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    this.loading = false;
                }
            });
    },

    close() {
        this.open = false;
        this.activeIndex = -1;
    },

    onKeydown(event) {
        if (!this.open) {
            return;
        }

        if (event.key === 'Escape') {
            this.close();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            const count = this.items.length;
            this.activeIndex = (this.activeIndex + delta + count + 1) % (count + 1) - 1;
            if (this.activeIndex === -1) {
                // Highlight wrapped back to the input itself.
            }
            return;
        }

        if (event.key === 'Enter' && this.activeIndex >= 0) {
            event.preventDefault();
            window.location.href = this.items[this.activeIndex].url;
        }
    },

    isActive(index) {
        return this.activeIndex === index;
    },
}));

Alpine.data('promptCopy', (rawBody) => ({
    copied: false,

    copy() {
        navigator.clipboard.writeText(rawBody).then(() => {
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2000);
        });
    },
}));

Alpine.data('promptViewer', (rawBody, names) => ({
    copied: false,
    variables: Object.fromEntries((names || []).map((name) => [name, ''])),

    get rendered() {
        let text = rawBody;
        for (const [name, value] of Object.entries(this.variables)) {
            if (!value) {
                continue;
            }
            const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            text = text.replace(new RegExp(`\\{\\{\\s*${escaped}\\s*\\}\\}`, 'g'), value);
        }
        return text;
    },

    copy() {
        navigator.clipboard.writeText(this.rendered).then(() => {
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2000);
        });
    },
}));

Alpine.data('promptForm', (config) => ({
    type: config.initialType || 'text',
    categoryId: config.initialCategoryId || '',
    selectedTools: config.initialTools || [],
    submitting: false,

    /** Pre-select the first suggested tool so the form validates on its own. */
    init() {
        if (this.selectedTools.length === 0) {
            this.selectedTools = (this.context.example_tools || []).slice(0, 1);
        }
    },

    get context() {
        return config.contexts[this.type] || config.contexts.text;
    },

    get guidance() {
        return this.context.guidance;
    },

    get bodyPlaceholder() {
        return this.context.body_placeholder;
    },

    get audiencePlaceholder() {
        return this.context.audience_placeholder;
    },

    /** Categories selectable for the current type (scope null = universal). */
    get categoryOptions() {
        return config.categories.filter(
            (category) => !category.type_scope || category.type_scope === this.type,
        );
    },

    /** Chips to render: type defaults plus anything already selected. */
    get toolChips() {
        const names = [
            ...(this.context.example_tools || []),
            ...this.selectedTools,
        ];

        return [...new Set(names)];
    },

    switchType(type) {
        if (type === this.type) {
            return;
        }

        this.type = type;

        // Drop a category scoped to another type; keep universal ones.
        const current = config.categories.find((c) => c.id === this.categoryId);
        if (current && current.type_scope && current.type_scope !== type) {
            this.categoryId = '';
        }

        // Reset tool chips to the new type's first suggested tool.
        this.selectedTools = (this.context.example_tools || []).slice(0, 1);
    },

    toggleTool(tool) {
        if (this.selectedTools.includes(tool)) {
            // Never allow removing the last one — the DB requires >= 1.
            if (this.selectedTools.length > 1) {
                this.selectedTools = this.selectedTools.filter((t) => t !== tool);
            }

            return;
        }

        if (this.selectedTools.length < 4) {
            this.selectedTools = [...this.selectedTools, tool];
        }
    },

    isToolDisabled(tool) {
        return this.selectedTools.length >= 4 && !this.selectedTools.includes(tool);
    },

    onSubmit() {
        this.submitting = true;
    },
}));

Alpine.start();
