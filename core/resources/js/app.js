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
