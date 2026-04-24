<div align="center">

<img src="https://raw.githubusercontent.com/harsh-pratap-singh-rathore/CodeCanvas/main/public/assets/images/logo.png" alt="CodeCanvas Logo" width="120" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 20px;" />

<a href="https://git.io/typing-svg">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://readme-typing-svg.demolab.com?font=Outfit&weight=800&size=48&duration=4000&pause=1000&color=FFFFFF&center=true&vCenter=true&width=800&height=80&lines=CodeCanvas+V2;Intelligent+Design+Engine;Zero+Cloud+Costs;Local+AI+Generation">
    <img src="https://readme-typing-svg.demolab.com?font=Outfit&weight=800&size=48&duration=4000&pause=1000&color=000000&center=true&vCenter=true&width=800&height=80&lines=CodeCanvas+V2;Intelligent+Design+Engine;Zero+Cloud+Costs;Local+AI+Generation" alt="Typing SVG">
  </picture>
</a>

<p align="center">
  <img src="https://img.shields.io/badge/Ollama-000000?style=for-the-badge&logo=ollama&logoColor=white" alt="Ollama" />
  <img src="https://img.shields.io/badge/PHP_8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
  <img src="https://img.shields.io/badge/Vanilla_CSS-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS" />
</p>

</div>

---

<br>

<div align="center">
  <h3>Forget templates. Type your aesthetic, and let the intelligence engine build your frontend architecture natively on your GPU.</h3>
</div>

<br>

## ✨ Core Features

| 🧠 Unbound Intelligence | 🎨 Premium Aesthetics | ⚡ Zero Vender Lock-in |
| :---: | :---: | :---: |
| Generates absolutely unique front-end schemas. No two layouts are the same, bypassing repetitive templates. | Integrates deep mathematical padding, contrast rules, and modern glassmorphic/brutalist design systems. | **100% Free & Open Source.** You own every line of code generated to deploy wherever you wish. |

<br>

## 🎮 How Local Execution Works

CodeCanvas V2 has been aggressively re-architected away from API cloud providers. To protect your data and reduce API generation costs to absolute zero, **CodeCanvas uses your own hardware**.

<div align="center">
  <img src="https://raw.githubusercontent.com/harsh-pratap-singh-rathore/CodeCanvas/main/public/assets/images/logo.png" width="80" style="margin-right: 20px; opacity: 0.5;" />
  <img src="https://img.shields.io/badge/DeepSeek_Coder-0a0a0a?style=for-the-badge&logo=deepseek&logoColor=white" alt="Deepseek" />
  <img src="https://img.shields.io/badge/Meta_Llama_3-0467DF?style=for-the-badge&logo=meta&logoColor=white" alt="Llama 3" />
  <img src="https://img.shields.io/badge/Google_Gemma-EA4335?style=for-the-badge&logo=google&logoColor=white" alt="Gemma" />
</div>

<br>

---

## 🚀 Extreme Local Setup

CodeCanvas v2 is incredibly simple to boot locally on Windows or Mac:

### 1️⃣ Prepare Environment
Download and run **XAMPP**, **MAMP**, or **Herd**. Clone this repository into your `/htdocs` or public root directory:
```bash
git clone https://github.com/harsh-pratap-singh-rathore/CodeCanvas.git
```
Start both the **Apache Web Server** and **MySQL Server**.

### 2️⃣ Map The Database
Navigate to `http://localhost/phpmyadmin`. 
1. Create a schema titled `v2db`.
2. Import the local file: `storage/docs/database/AI_BUILDER_SCHEMA.sql`.
3. Check `config/database.php` in your codebase to ensure credentials align.

### 3️⃣ Start The Engine (Ollama)
Download and install [Ollama](https://ollama.com). Open your terminal to pull the open-source foundational LLM. We heavily recommend the brilliant Gemma 4 (31b) or DeepSeek architectures for advanced CSS structuring.
```bash
ollama run gemma:7b
```
Open up `api/generate.php` in your code editor. Locate line 90 inside the `$ollamaRequest` function and switch the model mapping variable to match your terminal:

```php
'model' => 'gemma:7b',
```

### 4️⃣ Construct
Visit `http://localhost/CodeCanvas/public/index.html`. 
Sign up via a local account or the Google OAuth pipeline, input a prompt like *"A brutalist developer portfolio with extreme padding and neon borders"*, and watch the engine compile.

---

<br>

<div align="center">
    <a href="https://github.com/harsh-pratap-singh-rathore/CodeCanvas/stargazers"><img src="https://img.shields.io/github/stars/harsh-pratap-singh-rathore/CodeCanvas?style=social" alt="Github Stars" /></a>
    <br><br>
    <i>Architected with extreme logic. Released under the MIT License.</i>
    <br>
    <b>&copy; 2026 CodeCanvas Team</b>
</div>
