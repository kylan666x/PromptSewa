import './bootstrap';

import Alpine from 'alpinejs';

// Alpine.js gives SPA-like interactivity with zero build complexity —
// ideal for cPanel deployment where compiled assets are simply uploaded.
window.Alpine = Alpine;

/**
 * F6 (v1.7.8) — navbar notification bell.
 *
 * cPanel has no websockets, so the dropdown polls the unread endpoint
 * every 60 seconds. First paint is server-rendered; the poll refreshes
 * count + list from the same JSON shape. The product word is
 * "Notifications" — no live-delivery vocabulary anywhere.
 */
Alpine.data('notificationBell', (config) => ({
    open: false,
    count: config.count,
    items: config.items,

    init() {
        setInterval(() => this.poll(), 60000);
    },

    poll() {
        fetch(config.url, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) return;
                this.count = data.count;
                if (data.items) this.items = data.items;
            })
            .catch(() => {});
    },
}));

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
    // S1b (v1.9.0): packs are searchable — the typeahead renders them
    // between prompts and creators.
    packs: [],
    creators: [],
    activeIndex: -1,
    controller: null,

    get items() {
        return [
            ...this.prompts.map((p) => ({ ...p, kind: 'prompt' })),
            ...this.packs.map((p) => ({ ...p, kind: 'pack' })),
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
            .then((response) => (response.ok ? response.json() : { prompts: [], packs: [], creators: [] }))
            .then((data) => {
                this.prompts = data.prompts || [];
                this.packs = data.packs || [];
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

/**
 * T13 (v1.5.0) / P1 (v1.7.1): bookmark heart — optimistic flip, POST
 * toggle, no reload. Server response confirms the final state (and repairs
 * races). The rose fill IS the saved state, not just a heavier stroke.
 *
 * H4 (v1.7.4): `saved` is the ONLY source of truth and it is SERVER-rendered
 * (config.saved comes from the view). The icons are two mutually exclusive
 * svgs toggled with x-show — deliberately NOT a `heartClass` utility pair.
 * Toggling two conflicting Tailwind utilities on one element leaves both in
 * the class attribute and the winner is decided by stylesheet order: in the
 * browser the heart went rose but stayed an outline (fill-none beat
 * fill-current). Do not reintroduce a class getter here.
 */
Alpine.data('bookmarkHeart', (config) => ({
    url: config.url,
    saved: config.saved ?? false,
    busy: false,
    toast: '',
    toastTimer: null,

    /**
     * H4 (v1.7.4): silent lies are banned. Any non-OK response (419 CSRF,
     * 401/403 session, 422 validation, 500) must REVERT the optimistic flip
     * and tell the user — the previous code only reverted when .json()
     * threw, and showed nothing, so a failed save looked exactly like a
     * successful one until the next refresh.
     */
    fail(message) {
        this.saved = !this.saved;
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = ''), 4000);
    },

    toggle() {
        if (this.busy) {
            return;
        }
        this.busy = true;
        this.saved = !this.saved; // optimistic

        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        fetch(this.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                // X-CSRF-TOKEN is read from the layout meta tag; never invent it.
                'X-CSRF-TOKEN': token ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    // 419 = CSRF token mismatch (session rotated); anything
                    // non-OK is equally untrustworthy.
                    this.fail(
                        response.status === 419
                            ? 'Save failed — your session expired. Reload and try again.'
                            : 'Save failed — try again',
                    );
                    return null;
                }

                return response.json();
            })
            .then((data) => {
                if (data === null) {
                    return;
                }
                this.saved = data.saved;
            })
            .catch(() => {
                this.fail('Save failed — try again');
            })
            .finally(() => {
                this.busy = false;
            });
    },
}));

/**
 * T3 (v1.7.3): reCAPTCHA v3 submit hook — runs grecaptcha.execute with the
 * form name as the action, writes the token into the hidden input, then
 * lets the submit proceed. No-op when grecaptcha is absent (provider off).
 */
Alpine.data('captchaToken', (config) => ({
    token: '',

    tokenize(event) {
        if (typeof grecaptcha === 'undefined' || ! this.$el.closest('form')) {
            return; // provider inactive — submit unmodified
        }

        event.preventDefault();

        grecaptcha.ready(() => {
            grecaptcha.execute(document.querySelector('meta[name="captcha-site-key"]')?.content ?? '', { action: config.action })
                .then((token) => {
                    this.token = token;
                    this.$el.closest('form').submit();
                });
        });
    },
}));

Alpine.data('promptForm', (config) => ({
    /** A1 (v1.7.2): the type lives on the SERVER-RENDERED radios; this is
     * a mirror derived from the checked one (the radios submit name="type"). */
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

    /** Chips to render: type defaults, the modality-fit registry, plus anything already selected. */
    get toolChips() {
        const names = [
            ...(this.context.example_tools || []),
            ...(this.registryTools || []).map((tool) => tool.name),
            ...this.selectedTools,
        ];

        return [...new Set(names)];
    },

    /**
     * P2 (v1.7.1) / A2 (v1.7.2): chips as {name, modality} entries so the
     * template can carry data-modality + the gate expression — the served
     * HTML carries the gate; Alpine evaluates it on type change. Selected
     * tools always stay listed (even modality-invalid) so a creator can
     * see and remove a now-invalid chip. Type-default chips with no
     * registry entry carry the current type as their modality.
     */
    get toolEntries() {
        const entries = new Map();

        for (const tool of config.tools || []) {
            entries.set(tool.name, { name: tool.name, modality: tool.modality });
        }

        for (const name of this.context.example_tools || []) {
            if (! entries.has(name)) {
                entries.set(name, { name, modality: this.type });
            }
        }

        for (const name of this.selectedTools) {
            if (! entries.has(name)) {
                entries.set(name, { name, modality: this.type });
            }
        }

        return [...entries.values()];
    },

    /**
     * T6 (v1.5.0): registry tools offered for the current type — only
     * active tools whose modality matches (or is 'any'). Selected tools
     * always stay listed even when the type changes, so a creator sees
     * (and can remove) a now-invalid chip instead of it vanishing.
     */
    get registryTools() {
        return (config.tools || []).filter(
            (tool) => tool.is_active
                && (tool.modality === 'any' || tool.modality === this.type
                    || this.selectedTools.includes(tool.name)),
        );
    },

    /** A1 (v1.7.2): the checked radio IS the submitted state — this only
     * mirrors it for Alpine so x-show regions stay in sync.
     * A2: also toggles the per-option category gates and the cover-block
     * show/hide (data-cover-only) so the enhancement applies immediately. */
    switchType(type) {
        if (type === this.type) {
            return;
        }

        this.type = type;

        // Server truth: the matching radio becomes the checked one.
        const form = this.$el.closest('form') ?? document;
        const radio = form.querySelector(`input[type="radio"][name="type"][value="${type}"]`);
        if (radio) {
            radio.checked = true;
        }

        // Drop a category scoped to another type; keep universal ones.
        const current = config.categories.find((c) => c.id === this.categoryId);
        if (current && current.type_scope && current.type_scope !== type) {
            this.categoryId = '';
        }

        // T6: drop selected tools whose modality excludes the new type.
        const usable = (config.tools || [])
            .filter((tool) => tool.modality === 'any' || tool.modality === type)
            .map((tool) => tool.name);
        const kept = this.selectedTools.filter((name) => usable.includes(name));
        this.selectedTools = kept.length > 0
            ? kept
            : (this.context.example_tools || []).slice(0, 1);
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

/**
 * R5 (v1.7.7 Bug Hunt Raid) — the admin combobox picker.
 *
 * The option list is SERVER-RENDERED once (277 prompts render once, per the
 * founder ruling) and this only filters visibility client-side. Keyboard:
 * ArrowUp/Down move the highlight, Enter picks, Escape closes; the value
 * lands in the hidden input the form submits. The selected item shows as a
 * chip with a clear button. No dependencies beyond Alpine.
 */
Alpine.data('comboboxPicker', (config) => ({
    open: false,
    query: '',
    value: config.value ?? '',
    chip: config.chip ?? '',

    options() {
        return Array.from(this.$refs.list?.querySelectorAll('[role="option"]') ?? []);
    },

    filter() {
        const q = this.query.trim().toLowerCase();

        this.options().forEach((option) => {
            option.hidden = q !== '' && ! (option.dataset.search || '').includes(q);
            option.classList.remove('bg-paper-deep');
        });
    },

    move(delta) {
        const visible = this.options().filter((option) => ! option.hidden);
        if (visible.length === 0) {
            return;
        }

        const current = visible.findIndex((option) => option.classList.contains('bg-paper-deep'));
        const next = Math.max(0, Math.min(visible.length - 1, current === -1 ? 0 : current + delta));

        visible.forEach((option) => option.classList.remove('bg-paper-deep'));
        visible[next].classList.add('bg-paper-deep');
        visible[next].scrollIntoView({ block: 'nearest' });
    },

    pick() {
        const visible = this.options().filter((option) => ! option.hidden);
        const active = visible.find((option) => option.classList.contains('bg-paper-deep')) ?? visible[0];

        if (active) {
            this.choose(active);
        }
    },

    choose(option) {
        this.value = option.dataset.value ?? '';
        this.chip = option.dataset.chip ?? '';
        this.query = '';
        this.open = false;
        this.filter();
        this.focusInput();
    },

    clear() {
        this.value = '';
        this.chip = '';
        this.query = '';
        this.filter();
        this.focusInput();
    },

    focusInput() {
        this.$nextTick(() => this.$refs.input?.focus());
    },
}));

Alpine.start();
