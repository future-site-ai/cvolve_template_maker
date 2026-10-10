# CVolve Template Maker

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4_LTS-000000.svg?logo=symfony)](https://symfony.com/)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED.svg?logo=docker&logoColor=white)](docker-compose.yml)
[![GitHub stars](https://img.shields.io/github/stars/future-site-ai/cvolve_template_maker?style=social)](https://github.com/future-site-ai/cvolve_template_maker)

A web studio for designers to create CV templates for **[CVolve](https://github.com/future-site-ai/cvolve)**, by **Future Site**. Write HTML with simple placeholders or Twig, import existing designs, or let your own AI (Claude, ChatGPT, Gemini, Mistral, Ollama, any OpenAI-compatible API) generate and refine templates. Export a validated `.cvolve` package and install it in CVolve.

- ✍️ **Studio**: CodeMirror editor for the markup, `style.css`, `schema.json` and `manifest.json`; autosave; live A4 preview with page-break guides; sample CVs (typical, long, minimal) in eight languages including right-to-left Arabic; exact PDF preview through Chromium.
- 🧩 **Two markup syntaxes**: **Mustache HTML** (`{{header.name}}`, `{{#regions.main}}…{{/regions.main}}`) for HTML designers, or **Twig** for full control. Switch per project.
- 📥 **Import**: `.cvolve` packages, HTML with Mustache placeholders, Twig HTML, **JSON Resume themes** (Handlebars or Mustache), zips with `index.html` + CSS + fonts, and **finished static HTML CVs converted by AI**.
- ✨ **Generate from a brief**: describe the design; your AI writes a complete template that is validated (with one automatic repair round).
- 🤖 **Design assistant**: ask for changes in plain language; every answer is a diff you **Apply** or **Discard**. A revision is saved before each applied change.
- 🔑 **Bring your own key**: each designer connects their own AI accounts. Keys are encrypted at rest (libsodium) and never shown again.
- ✅ **Same engine as CVolve**: sandbox, validator and renderer come from CVolve's shared template kit, so what passes here installs there.
- 🕓 **Revisions, assets, thumbnails**: upload fonts (declared in the manifest automatically), render a gallery thumbnail, restore any revision.

![The template studio: code editor, live A4 preview and the AI design assistant proposing a change](docs/screenshots/studio-ai-assistant.jpg)

## 📸 Screenshots

| My templates | Mustache HTML in the studio (long sample CV) |
|---|---|
| ![Dashboard with template thumbnails](docs/screenshots/dashboard.jpg) | ![Editing a Mustache template with live preview](docs/screenshots/studio-mustache.jpg) |

| Generate with AI | Import any format |
|---|---|
| ![Generate a template from a design brief](docs/screenshots/generate.jpg) | ![Import .cvolve, HTML, JSON Resume themes or finished CVs](docs/screenshots/import.jpg) |

| AI connections (bring your own key) |
|---|
| ![Designers connect their own Claude, ChatGPT or Ollama accounts](docs/screenshots/ai-connections.jpg) |

## Quick start (Docker)

The maker uses CVolve's template kit (`cvolve/packages/template-kit`), so keep both repositories side by side:

```
Future Site Github/
├── cvolve/
└── cvolve_template_maker/
```

```bash
cd cvolve_template_maker
cp .env.example .env.docker      # set APP_SECRET and MAKER_ENCRYPTION_KEY
docker compose up -d --build
```

Open **http://localhost:8090** and log in as `designer@cvolve.local` / `designer1234` (change `MAKER_ADMIN_*` in `.env.docker`), or sign up.

| Setting | Meaning |
|---|---|
| `MAKER_ENCRYPTION_KEY` | Encrypts designers' API keys. **Set a long random value and never change it**; changing it makes stored keys unreadable. Falls back to `APP_SECRET`. |
| `MAKER_OPEN_REGISTRATION` | `1` = anyone can sign up, `0` = accounts only via `app:create-user` |
| `MAKER_ADMIN_EMAIL` / `_PASSWORD` | First account, created on start when missing |
| `GOTENBERG_URL` | Chromium renderer for PDF previews and thumbnails |
| `MAKER_PORT` | Host port (shell environment, default 8090) |

## Workflow

1. **Start**: a blank Mustache or Twig starter, a copy of a CVolve preset, an import, or *Generate with AI*.
2. **Design** in the studio: edit files on the left, see the A4 preview on the right, fix anything listed under **Problems**.
3. **Ask the assistant** for changes (pick one of your AI connections in the chat header), review the diff, apply.
4. **Test** with the long sample (page breaks), the minimal sample and other languages; open **PDF** for the exact output.
5. **Export .cvolve** and install it in CVolve under *Templates → Import* (or as an admin preset).

### AI connections

Add them under **AI connections**: Anthropic, OpenAI, Gemini, Mistral, DeepSeek, xAI, Ollama (local, no key), or any OpenAI-compatible endpoint (OpenRouter, Groq, LM Studio… with a base URL). Use **Test** to check the key. Template work needs a capable model; small local models often fail validation.

## Template formats

The [CVolve README](https://github.com/future-site-ai/cvolve#template-formats-cvolve-imports) has the full specification: Mustache placeholders, Twig variables, sandbox rules, `schema.json` and `manifest.json`. The **Docs** page in the maker shows the same contract the AI models receive.

## Development

```bash
composer install                      # links ../cvolve/packages/template-kit
make local-deps                       # PostgreSQL :5433, Gotenberg :3001
cat > .env.local <<'ENV'
DATABASE_URL="postgresql://maker:maker@127.0.0.1:5433/maker?serverVersion=16&charset=utf8"
GOTENBERG_URL=http://127.0.0.1:3001
MAKER_ENCRYPTION_KEY=some-long-random-dev-key
ENV
php bin/console doctrine:migrations:migrate -n
php bin/console app:create-user you@example.com
php -S 127.0.0.1:8090 -t public
make qa                               # PHPUnit + PHPStan
```

## Architecture

```
src/
├── Entity/      User, AiConnection (encrypted key), TemplateProject, ProjectAsset, ProjectRevision, ChatMessage
├── Service/
│   ├── Ai/      AiClient (Neuron AI, per-connection providers), SecretBox (libsodium)
│   ├── Project/ ProjectService (compile, validate, preview, export, revisions), DesignChat, Starters
│   └── Export/  GotenbergClient (PDF preview, thumbnails)
├── Controller/  projects & studio, import, generate, AI connections, auth, docs
└── Security/    OwnerVoter (designers only see their own work)
assets/controllers/  studio (CodeMirror 6, autosave, preview), design-chat, …
```

Shared from CVolve's kit (`Cvolve\TemplateKit`): `TemplateSandbox`, `TemplatePackageReader`/`Writer`, `DocumentRenderer`, `SampleCv`, `TemplateImporter` (all formats), `TemplateAi` (convert, generate, edit with validation and repair), `Prompts`.

## 📄 License

CVolve Template Maker is open source under the [MIT License](LICENSE), © Future Site Di Haidar Anbar. You are free to use, modify, and distribute it, including commercially, as long as the copyright and license notice are kept.

## 🙌 Using This Project?

If you use CVolve Template Maker or build on its source code, please **credit the project by linking back to this repository**:

```markdown
Based on [CVolve Template Maker](https://github.com/future-site-ai/cvolve_template_maker) by Future Site Di Haidar Anbar.
```

---

## ⭐ Support the Project

If you find CVolve Template Maker useful, please kindly:

- ⭐ **[Star this repository](https://github.com/future-site-ai/cvolve_template_maker)**. It helps others discover the project.
- 👤 **[Follow me on GitHub](https://github.com/future-site-ai)** for more open-source projects.
- 🔗 **Link back to [this repo](https://github.com/future-site-ai/cvolve_template_maker)** whenever you use or share this source code.

Thank you for your support! 💚
