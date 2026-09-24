# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Project: PromptSewa

### 1. Product Vision
PromptSewa is a premium, highly scalable Prompt Library and Marketplace built on PHP (Laravel). It allows users to discover, test, buy, sell, and version-control AI prompts. It outperforms competitors by offering a built-in Prompt Playground, semantic search, deep gamification, and a frictionless cPanel-compatible deployment architecture.

### 2. Target Audience
- **Creators:** Prompt engineers looking to monetize and showcase their work.
- **Consumers:** Developers, marketers, and businesses seeking high-quality, tested prompts.
- **Teams:** Agencies needing a centralized, version-controlled vault for their internal AI instructions.

### 3. Core Features
1. **Marketplace & Monetization:** eSewa / manual payments integration for buying/selling, creator dashboards, affiliate links, and tiered licensing (Personal vs. Commercial).
2. **The Prompt Playground:** An in-browser UI where users can test prompts against various LLM APIs using their own API keys (keys are encrypted and never stored in plain text).
3. **Version Control & Forking:** Git-like history for prompts. Users can "fork" a prompt, create a new branch, and submit "pull requests" to the original creator.
4. **Gamification & Profiles:** XP system, badges (e.g., "Top Seller", "Syntax Wizard"), customizable profile banners, and leaderboards.
5. **Community:** Integrated forum, prompt reviews, upvoting/downvoting, and a curated blog.
6. **Semantic Search:** Vector-based search to find prompts by intent, not just exact keyword matches.

### 4. Technical Stack (cPanel Optimized)
- **Backend:** PHP 8.2+, Laravel 12.
- **Frontend:** Blade Templates, Tailwind CSS, Alpine.js (No heavy SPA framework to avoid Node.js server requirements).
- **Database:** MySQL/MariaDB (Standard on cPanel).
- **Search:** Meilisearch (if VPS) OR MySQL Full-Text Search with `MATCH()` against a dedicated tags/description column (for cheap shared hosting).
- **Queues & Cache:** `database` driver for both. (Strictly NO Redis/Memcached to ensure cheap cPanel compatibility).
- **Storage:** Local `storage/app/public` with symlink, or Backblaze B2 (S3 compatible) for cheap off-server storage.
- **Deployment:** Apache with `.htaccess` rewrites. No Docker. No Supervisor. Cron jobs handled via cPanel UI.

### 5. User Roles & Permissions (RBAC)
- **Guest:** Browse public prompts, read blog/forum.
- **Member:** Download free prompts, use playground, participate in forums, earn XP.
- **Creator:** All Member perks + upload prompts, manage pricing, view analytics, receive payouts.
- **Moderator:** Manage forum, review reported prompts, ban users.
- **Admin:** Full system access, manage categories, configure Stripe, view global analytics.

### 6. cPanel Deployment Strategy
- The `public` directory contents will be moved to `public_html`.
- The core Laravel files will reside in a hidden folder (e.g., `/home/user/core/`).
- A custom `index.php` and `.htaccess` in `public_html` will point to the core folder.
- Queues will be processed via a Laravel Scheduled Task running every minute via cPanel Cron Jobs (`* * * * * cd /home/user/core && php artisan schedule:run`).
