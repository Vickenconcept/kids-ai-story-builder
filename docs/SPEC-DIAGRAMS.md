# AI Children’s Storybook App — Architecture Diagrams

This document matches exported **SVG** and **PNG** assets in [`diagrams/`](./diagrams/). The checked-in PNG/SVG were generated from the `.mmd` sources (see **Regenerating** below).

---

## 1. System architecture (Laravel + Inertia + Vue)

![System architecture](./diagrams/01-system-architecture.svg)

```mermaid
flowchart TB
  subgraph Client["Browser"]
    UI[Vue + Inertia pages]
    Viewer[Turn.js story viewer]
    UI --> Viewer
  end

  subgraph Laravel["Laravel"]
    Web[Web routes + controllers]
    Jobs[Queue workers]
    API[Internal services / AI orchestration]
    Web --> API
    API --> Jobs
  end

  subgraph Storage["Media"]
    S3[(Object storage e.g. S3 / Cloudinary)]
    DB[(Database)]
  end

  subgraph External["External APIs"]
    OAI[OpenAI: text / image / TTS]
    VID[Optional: Runway / Kaiber video]
  end

  UI <--> Web
  Jobs --> OAI
  Jobs --> VID
  Jobs --> S3
  Web --> DB
  Jobs --> DB
  Viewer --> S3
```

---

## 2. AI generation pipeline (async jobs)

![AI pipeline](./diagrams/02-ai-pipeline.svg)

```mermaid
flowchart LR
  A[User input: topic, age, length, style] --> B[Generate story text per page]
  B --> C[Generate image per page]
  C --> D[Generate narration audio per page]
  D --> E{Pro: video?}
  E -->|Yes| F[Generate short clip per page]
  E -->|No| G[Store assets + mark complete]
  F --> G
```

---

## 3. User flow

![User flow](./diagrams/03-user-flow.svg)

```mermaid
flowchart TD
  S1[1. Setup: topic, lesson type, age, length, style] --> S2[2. Generate: queued AI pipeline]
  S2 --> S3[3. Preview & edit: text / regen image / regen audio]
  S3 --> S4[4. Export: PDF / Turn.js bundle / optional full video]
```

---

## 4. Core domain (entities)

![Domain model](./diagrams/04-domain-erd.svg)

```mermaid
erDiagram
  User ||--o{ StoryProject : owns
  StoryProject ||--o{ StoryPage : contains
  StoryProject ||--o{ AIJob : tracks
  User ||--o{ FeatureLevel : "plan / credits"

  User {
    int id
    string email
    int credits
  }
  StoryProject {
    int id
    string title
    string topic
    string status
  }
  StoryPage {
    int id
    int page_number
    text text_content
    string image_url
    string audio_url
    string video_url
    json quiz_questions
  }
  AIJob {
    int id
    string type
    string status
    json payload
  }
```

---

## 5. Feature tiers (positioning)

![Feature tiers](./diagrams/05-feature-tiers.svg)

```mermaid
flowchart TB
  subgraph Basic["Basic tier"]
    B1[Text + images]
    B2[Optional narration]
    B3[PDF + Turn.js export]
  end
  subgraph Pro["Pro / upsell"]
    P1[Page video]
    P2[Quizzes per page]
    P3[Voices / languages]
    P4[Higher limits or unlimited]
  end
```

---

## Regenerating SVG and PNG

Edit the sources in `docs/diagrams/*.mmd`, then pick one approach.

### Option A — Kroki (no local install; needs network)

PowerShell from the repo root:

```powershell
$dir = "docs/diagrams"
$files = @('01-system-architecture','02-ai-pipeline','03-user-flow','04-domain-erd','05-feature-tiers')
foreach ($name in $files) {
  $path = Join-Path $dir "$name.mmd"
  $body = [System.IO.File]::ReadAllText((Join-Path (Get-Location) $path), [System.Text.Encoding]::UTF8)
  Invoke-RestMethod -Uri "https://kroki.io/mermaid/svg" -Method Post -Body $body -ContentType "text/plain; charset=utf-8" -OutFile (Join-Path $dir "$name.svg")
  Invoke-RestMethod -Uri "https://kroki.io/mermaid/png" -Method Post -Body $body -ContentType "text/plain; charset=utf-8" -OutFile (Join-Path $dir "$name.png")
}
```

### Option B — Mermaid CLI (local; uses Puppeteer/Chromium)

From `docs/diagrams` with Node.js:

```bash
npx --yes @mermaid-js/mermaid-cli@latest -i 01-system-architecture.mmd -o 01-system-architecture.svg
npx --yes @mermaid-js/mermaid-cli@latest -i 01-system-architecture.mmd -o 01-system-architecture.png
```

Repeat for each `*.mmd` file.
