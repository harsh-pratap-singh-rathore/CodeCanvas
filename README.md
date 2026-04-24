<div align="center">
    <br>
    <h1>CodeCanvas v2</h1>
    <p><b>Intelligent Design Engine. Zero cloud costs. 100% Local AI Frontend Generation.</b></p>
    <br>
</div>

CodeCanvas v2 is an open-source, mathematically structured web application that turns plain text prompts into completely unique, beautifully designed, and structurally sound portfolio websites. It operates completely **locally**, leveraging open-source large language models through Ollama instead of relying on expensive cloud proxies.

Forget templates. Forget dragging blocks. Type your aesthetic, and let the intelligence engine build your frontend architecture in 60 seconds.

## ✨ Features (v2 Core)

- **The Unbound Engine:** CodeCanvas v2 uses a strictly controlled prompt architecture that enforces user-intent (colors, typography, layout) while introducing controlled creative variation to guarantee that **no two outputs are structurally identical**.
- **Bring Your Own Intelligence:** Fully decoupled from restrictive commercial APIs. Plugs directly into your local **Ollama** server.
- **Auto-Responsive Layouts:** Employs advanced CSS grid and flexbox logic generated dynamically by the LLM. 
- **Premium Aesthetics by Default:** Built heavily around polished, modern web principles. Whether generating Brutalism, Apple Minimal, or Neon Glassmorphism, CodeCanvas respects padding, contrast, and layout weight.
- **100% Live-Editable:** A proprietary HTML parsing system that preserves `data-edit` attributes, meaning every pixel of generated code is instantly editable in the integrated graphical builder without touching the raw HTML.
- **Zero Vender Lock-in:** 100% ownership. Export your static assets and host them anywhere for free.

## ⚙️ Tech Stack

- **Backend:** PHP 8.2 (Secure PDO, Strict JSON Routing)
- **Database:** MySQL
- **Frontend / Dashboard:** Vanilla JavaScript, Glassmorphic CSS Engine
- **Generative Intelligence:** Any code-optimized open source model (Default: Gemma 4 / DeepSeek-Coder-V2) via Groq and Ollama.

---

## 🚀 How to Run Locally

CodeCanvas v2 is designed to run locally on your own machine. 

### Step 1: Environment Setup
1. Download identifying web-server software like **XAMPP**, **MAMP**, or **Herd**.
2. Clone this repository into your local webroot directory (e.g., `C:\xampp\htdocs\CodeCanvas`).
3. Start the **Apache** and **MySQL** server processes.

### Step 2: Database Initialization
1. Navigate to your MySQL client (like phpMyAdmin at `http://localhost/phpmyadmin`).
2. Create a new database named `v2db`.
3. Import the SQL file located at `storage/docs/database/AI_BUILDER_SCHEMA.sql` to construct the tables.
4. Open the `config/database.php` script and verify identical connection parameters.

### Step 3: Local AI Configuration
CodeCanvas generates massive architectural strings on the fly. To prevent network costs, it uses **Ollama**.
1. Install [Ollama](https://ollama.com) on your machine.
2. Pull a highly capable code model in your terminal. We highly recommend Gemma or DeepSeek:
   ```bash
   ollama run gemma:7b
   ```
3. In `api/generate.php`, around line 90, modify the `model` parameter inside `ollamaRequest()` to exactly match the model you just pulled:
   ```php
   'model' => 'gemma:7b',
   ```

*(Note: The first stage enhancement pipeline uses Groq for instant-JSON processing. Ensure `GROQ_API_KEY` is present in your `.env` configuration file to process the structural blueprints).*

### Step 4: Launch
Navigate to `http://localhost/CodeCanvas/public/index.html` in your browser. 
Sign up via standard email or Google OAuth, load the dashboard, input a prompt, and watch your intelligence engine build.

---

<div align="center">
    <i>Built with extreme attention to detail. Open Source under the MIT License.</i>
    <br>
    &copy; 2026 CodeCanvas Team.
</div>
