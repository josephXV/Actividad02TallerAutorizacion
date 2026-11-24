# Tarea de Joseph – base del proyecto y reflexión

**Rama:** `joseph/reflexion` · **Reflexión:** preguntas 1 y 2

> Tu parte principal es la **base**, que subes directo a `main` con el primer commit (ver los comandos de creación del repositorio que te dio Claude). Esta guía cubre tu sección de la reflexión, que va por PR como la de los demás.

## 1. Preparar el entorno

Trabajas desde la carpeta local donde creaste el repositorio (ya tiene `.git` y `main`):

```bash
cd /home/macho/api-academica-repo
git checkout main
git pull origin main
git checkout -b joseph/reflexion
```

## 2. Archivos a modificar

### 1. Reflexión: `entrega/Reflexion_Seguridad.md` (preguntas 1 y 2)

Reemplaza **solo** la línea `_Pendiente: Joseph_` que está entre `<!-- INICIO JOSEPH (preguntas 1 y 2) -->` y `<!-- FIN JOSEPH -->` por:

```markdown
**1. Autenticación vs. autorización.** La autenticación comprueba *quién* es el usuario (login, token de Sanctum). La autorización decide *qué puede hacer* ese usuario ya identificado (Gates, Policies, roles, permisos). Sanctum autentica; no autoriza.

**2. ¿Cuándo usar Gates?** Para reglas generales que no dependen de un registro concreto, como `access-admin-dashboard` o `access-professor-dashboard`: acceso a módulos, paneles, reportes o funciones globales.
```

## 3. Cómo probarlo

Es un archivo de documentación: revisa que se vea bien con `git diff` y que no hayas tocado otras secciones.

## 4. Commit, push y Pull Request

```bash
git add entrega/Reflexion_Seguridad.md
git commit -m "feat: reflexión de seguridad, preguntas 1 y 2"
git push -u origin joseph/reflexion
gh pr create --base main --head joseph/reflexion --title "feat: reflexión preguntas 1 y 2" --body "Sección de Joseph de la reflexión de seguridad (Actividad 5)."
```

## 5. Tu papel como dueño del repositorio

Como administras `main`, haz el merge de los PR en este orden (cada uno depende del anterior):

1. Diana (`diana/gates-dashboards`)
2. Franklin (`franklin/estudiantes-policy`)
3. Emesis (`emesis/coordinador-evidencias`)
4. Jairo (`jairo/abac-sede-tests`)

Tu PR de reflexión puede ir en cualquier momento (solo toca tu sección). Después de cada merge: `git checkout main && git pull origin main`. Al terminar, ejecuta `php artisan migrate:fresh --seed && php artisan test` en `main`: deben pasar las 18 pruebas.
