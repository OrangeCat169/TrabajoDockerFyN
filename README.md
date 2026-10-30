# To-Do Cloud — Arquitectura y Despliegue Continuo (CI/CD)

Aplicación web de gestión de tareas desarrollada sobre arquitectura de contenedores con **Docker Compose**, con despliegue automatizado en una máquina virtual de **Azure** mediante **GitHub Actions**.

---

## 🏗️ Arquitectura del Sistema

La solución está compuesta por dos servicios contenedorizados y un pipeline de CI/CD:

1. **Frontend / Backend (`app`):** Aplicación en PHP 8.x servida por Apache que expone la interfaz de usuario y la lógica de interacción con la base de datos.
2. **Base de Datos (`bd`):** Contenedor MariaDB 11 con almacenamiento persistente en volumen Docker (`datos_mariadb`) y scripts de inicialización y migración (`init.sql` y `migration.sql`).
3. **Pipeline CI/CD:** GitHub Actions para el despliegue automático vía SSH al hacer merge en la rama `main`.

---

## 🛠️ Prerrequisitos de Infraestructura

Para replicar este despliegue se requiere:

* **Servidor VM en Azure (Ubuntu 22.04 LTS):**
  * Dirección IP Pública accesible.
  * Puerto 80 (HTTP) y Puerto 22 (SSH) abiertos en el Grupo de Seguridad de Red (NSG).
  * Docker Engine y Docker Compose V2 instalados en la VM.
* **Repositorio en GitHub:**
  * Reglas de protección en la rama `main` (Pull Requests obligatorios y aprobación de pares).
  * Variables de entorno / Secrets configurados en GitHub Actions:
    * `AZURE_VM_HOST`: IP pública de la máquina virtual (`20.201.122.136`).
    * `AZURE_VM_USER`: Usuario SSH de la máquina virtual.
    * `AZURE_VM_SSH_KEY`: Clave privada SSH para autenticación.

---

## 🚀 Variables de Entorno

La aplicación utiliza las siguientes variables de entorno dentro del archivo `docker-compose.yml`:

| Variable | Descripción | Valor por Defecto |
| :--- | :--- | :--- |
| `DB_HOST` | Host del servicio de Base de Datos | `bd` |
| `DB_NAME` | Nombre de la base de datos | `nube` |
| `DB_USER` | Usuario de la base de datos | `root` |
| `DB_PASSWORD` | Contraseña del usuario | `clase123` |
| `APP_VERSION` | Commit Hash corto asignado en CI/CD | `${GITHUB_SHA:-1.0.0}` |

---

## 🗄️ Esquema de Base de Datos y Migración

La tabla principal `tareas` cuenta con la siguiente estructura:

* `id` (INT, PK, AUTO_INCREMENT)
* `titulo` (VARCHAR 150)
* `completada` (BOOLEAN, DEFAULT FALSE)
* `prioridad` (VARCHAR 10, DEFAULT 'media')
* `categoria` (VARCHAR 50, DEFAULT 'General')
* `fecha_vencimiento` (DATETIME, NULL)
* `creada_en` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

Los scripts situados en `./database/` se ejecutan automáticamente al iniciar el contenedor por primera vez mediante montajes de volumen en `/docker-entrypoint-initdb.d/`.

---

## ⚙️ Pasos para Desplegar desde Cero (Local / Servidor)

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/OrangeCat169/TrabajoDockerFyN.git](https://github.com/OrangeCat169/TrabajoDockerFyN.git)
   cd TrabajoDockerFyN