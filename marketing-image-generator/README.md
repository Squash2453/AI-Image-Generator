# Marketing Image Studio (AI-Powered Visual Generator)

A dedicated, full-stack marketing image studio powered **exclusively by OpenRouter** on the backend. Built with Vanilla PHP 8, MySQL (`marketing_ai_db`), and a responsive SPA frontend tailored for high-converting marketing creatives, advertising campaigns, and social media visuals.

---

## 🎯 Features & Requirements Implemented

### 1. Interactive Marketing Chat Interface
- **Modern Chat Stream:** Seamless ChatGPT / Midjourney style conversational interface.
- **Marketing Parameters:**
  - **Aspect Ratios:** `1:1` Square (Feed/Product), `16:9` Landscape (Website Hero), `9:16` Portrait (Reels/Stories), `4:5` Social Portrait, `4:3` Standard.
  - **Visual Styles:** Commercial Studio Product, High-Converting Social Ad, Website Hero Banner, Luxury Editorial, Cyberpunk Neon Tech, 3D Isometric Render, Authentic Lifestyle/UGC.
- **Image Actions:**
  - Full-screen interactive Lightbox with high-res zoom.
  - One-click Download button.
  - Copy Image URL / Prompt.
  - "Iterate" quick-action button for rapid visual refinement.

### 2. Comprehensive Telemetry for the Entire Chat History
- **Message-Level Telemetry:** Every generation displays a real-time badge:
  - ⚡ **Roundtrip Latency (ms / s)**
  - 🪙 **Token Usage** (Prompt, Completion, Image Tokens, Total Tokens)
  - 💵 **Cost (USD)**
  - 🏷️ **Model ID & HTTP Status Code**
- **Session Telemetry Pill:** Real-time summary header on active chats showing total calls, cumulative spend, and average latency.
- **Dedicated Telemetry & Analytics Dashboard:**
  - KPI Cards: Total Generations, Total Spend ($), Total Tokens, Average Latency, Success Rate (%).
  - Daily Activity Bar Chart (Last 14 days).
  - Model Distribution Doughnut Chart.
  - Audit Log Table: Filterable list of all API transactions with model, latency, tokens, cost, status, and chat link.
  - **CSV Export:** Download full telemetry audit logs as `.csv`.

### 3. Access Control List (ACL) for Admins & Models
- **Model ACL:**
  - Admin controls which OpenRouter models are enabled.
  - Granular `access_level`: **All Users** vs. **Admins Only** (e.g. reserving expensive models like FLUX.1 Dev or Gemini 3 Pro for admins while providing free/low-cost models like Recraft v4.1 or Gemini 2.5 to all users).
  - Enforced server-side in PHP backend (`api/chat.php` & `can_user_access_model()`); unauthorized requests return `403 Forbidden`.
  - Admin can register new OpenRouter models dynamically into the ACL catalog.
  - Admin can assign default models.
- **User ACL:**
  - Role management (`admin` vs. `user`).
  - Account status controls (`active` vs. `disabled`).
  - View individual user spend, token consumption, and generation counts.

### 4. Pure OpenRouter Backend with Two-Way UI-to-.ENV Mapping
- **100% OpenRouter:** Strictly uses OpenRouter endpoints for all generation and model queries.
- **Two-Way UI-to-.ENV Sync:**
  - Admin UI page: `OpenRouter .ENV Setup`.
  - Admins can configure: `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`, `OPENROUTER_DEFAULT_MODEL`, `OPENROUTER_SITE_NAME`, `OPENROUTER_SITE_URL`, `OPENROUTER_TIMEOUT`.
  - Saving from the UI writes directly to the `.env` file, updates PHP runtime environment (`putenv`, `$_ENV`), and syncs with the `system_settings` table in MySQL.
  - Includes a live **Test Connection** button that pings OpenRouter and validates latency, status, and model availability in real time.

### 5. Chat Continuation of Previously Generated Images
- **Thread Persistence:** Chat sessions are saved in MySQL and displayed in the left sidebar grouped by activity.
- **Contextual Continuation Engine:** When a user continues chatting in an existing thread (e.g., *"Make the background darker with golden studio lighting"* or *"Add a 50% discount badge"*):
  - Backend extracts prior design context and subject matter from previous messages in the thread.
  - Synthesizes a refined prompt maintaining core visual consistency while applying the user's iterative instructions.
  - Appends new iterations to the chat thread with independent telemetry.

---

## 🚀 Quick Start & Access

### URL
```
http://localhost/AI-Image-Generator/marketing-image-generator/
```

### Default Accounts
| Role | Email | Password | ACL Level |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@marketing.local` | `Admin@12345` | Full System & All Models |
| **User (Marketer)** | `marketer@marketing.local` | `User@12345` | Permitted Models Only |

*(Both accounts are pre-seeded with quick one-click autofill buttons on the login screen).*

---

## 📁 Project Structure

```
marketing-image-generator/
├── .env                              # Environment configuration (synced with UI)
├── index.php                         # Modern SPA Marketing Workspace & Dashboards
├── login.php                         # Authentication page with quick demo creds
├── register.php                      # User registration page
├── logout.php                        # Session logout handler
├── README.md                         # Documentation
├── includes/
│   ├── db.php                        # PDO database connection to marketing_ai_db
│   ├── env.php                       # Two-way UI-to-.env reader/writer
│   ├── auth.php                      # Session auth & Model/User ACL enforcement
│   ├── openrouter.php                # Pure OpenRouter client with telemetry recorder
│   └── setup_db.php                  # Database migration & seeder
├── api/
│   ├── chat.php                      # Chat thread CRUD, message send & image generation
│   ├── telemetry.php                 # Telemetry metrics, aggregates, and CSV export
│   ├── acl.php                       # Model and User ACL endpoints
│   └── settings.php                  # OpenRouter config management & connection tester
└── uploads/                          # Stored marketing images (WebP/PNG/JPG)
```

---

## 🗄️ Database Schema (`marketing_ai_db`)

- `users`: User profiles, bcrypt password hashes, roles (`admin`, `user`), status (`active`, `disabled`).
- `models`: OpenRouter models catalog with `access_level` (`all`, `admin`), `is_active`, `is_default`, and cost rates.
- `chats`: Conversation threads with `uuid`, `user_id`, `title`, `marketing_goal`, and `default_model`.
- `messages`: Conversation prompts and assistant image generation replies with `image_path`, `aspect_ratio`, and `style_preset`.
- `telemetry`: Metrics table recording `latency_ms`, `prompt_tokens`, `completion_tokens`, `total_tokens`, `cost`, `status_code`, and request metadata.
- `system_settings`: Synchronized key-value storage for environment settings.
