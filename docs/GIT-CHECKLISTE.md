# Git-Workflow Checkliste

## 1. Status prüfen

```bash
git status
```

- **rot** = Änderungen noch **nicht** im Staging (untracked / modified)  
- **grün** = Änderungen liegen bereits im **Staging** (werden beim nächsten Commit gespeichert)  
- **weiß** = keine Änderungen

---

## 2. Änderungen in den Staging-Bereich legen

```bash
git add .
```

- `.` = alle Änderungen hinzufügen  
- oder gezielt:  

  ```bash
  git add path/to/file.php
  ```

---

## 3. Prüfen, was gestaged ist

```bash
git status
```

- Alles, was **grün** erscheint, kommt in den nächsten Commit.  
- Alles, was noch **rot** ist, wird **nicht** aufgenommen → ggf. nochmal `git add`.

---

## 4. Commit erstellen

```bash
git commit -m "Kurze Beschreibung, was geändert wurde"
```

- **Nur die grünen (gestagten) Dateien** landen im Commit.  
- Alles andere bleibt uncommitted und wartet weiter.

---

## 5. Push zum Remote (z. B. GitHub)

```bash
git push
```

- Schiebt deine Commits vom lokalen Branch in den entsprechenden Remote-Branch.

---

## 🔎 Tipp: Was passiert wann?

- **Ändern einer Datei** → Datei ist „modified“ (rot).  
- **`git add`** → legt aktuellen Stand in Staging (grün).  
- **Weiter ändern nach `git add`** → neue Änderungen sind wieder „modified“ (rot), die Version im Staging bleibt trotzdem drin → → **erneut `git add` nötig**, wenn du die neueste Version auch im Commit haben willst.  
- **`git commit`** → speichert nur das, was im Staging ist (grün).  
- **`git push`** → überträgt deine Commits ins Remote-Repo.

## Nachrichten (Commits) klar strukturieren

- `fix:` für Bugfixes
- `feature:` für neue Features
- `refactor:` für Code-Optimierungen
