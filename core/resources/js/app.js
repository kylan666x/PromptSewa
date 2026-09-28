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
 * Signup form (S4): live username suggestions from the display name and an
 * animated password strength meter. Purely advisory — the server re-checks
 * everything (required/unique handle, common-password + name/email floor).
 */
Alpine.data('signupForm', () => ({
    name: '',
    username: '',
    email: '',
    password: '',
    submitting: false,
    suggestions: [],

    init() {
        this.$watch('name', () => this.buildSuggestions());
    },

    buildSuggestions() {
        const slug = this.name.trim().toLowerCase()
            .replace(/[^a-z0-9\s_-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 24);

        if (slug.length < 4) {
            this.suggestions = [];
            return;
        }

        // First three variants; collisions with real accounts are caught
        // server-side on submit — these are nudges, not guarantees.
        this.suggestions = [slug, `${slug}-pro`, `${slug}${new Date().getFullYear()}`];
    },

    get hasUpperAndLower() {
        return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password);
    },

    get hasNumber() {
        return /[0-9]/.test(this.password);
    },

    get notNameOrEmail() {
        const lower = this.password.toLowerCase();
        const namePart = this.name.trim().toLowerCase();
        const emailPart = this.email.split('@')[0].toLowerCase();

        if (namePart.length >= 3 && lower.includes(namePart)) {
            return false;
        }
        if (emailPart.length >= 4 && lower.includes(emailPart)) {
            return false;
        }

        return true;
    },

    get strength() {
        const pw = this.password;
        let score = 0;

        if (pw.length >= 8) score++;
        if (pw.length >= 12) score++;
        const classes = (/[a-z]/.test(pw) ? 1 : 0) + (/[A-Z]/.test(pw) ? 1 : 0)
            + (/[0-9]/.test(pw) ? 1 : 0) + (/[^a-zA-Z0-9]/.test(pw) ? 1 : 0);
        if (classes >= 3) score++;
        if (classes >= 4 && pw.length >= 10) score++;
        if (/(?:0123|1234|2345|3456|4567|5678|6789|abcd|qwer|asdf|zxcv)/i.test(pw)) score--;
        if (/(.)\1{2,}/.test(pw)) score--;
        if (!this.notNameOrEmail) score = Math.min(score, 1);

        return Math.max(0, Math.min(4, score));
    },

    get meterWidth() {
        return this.strength * 25;
    },

    get meterColor() {
        return ['bg-rose-500', 'bg-rose-400', 'bg-saffron', 'bg-lime-500', 'bg-emerald-500'][this.strength];
    },

    get meterTextClass() {
        return ['text-rose-600', 'text-rose-500', 'text-saffron-deep', 'text-lime-600', 'text-emerald-600'][this.strength];
    },

    get meterLabel() {
        return ['Too weak', 'Weak', 'Okay', 'Strong', 'Excellent'][this.strength];
    },
}));

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
