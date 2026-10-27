@echo off
chcp 65001 >nul
REM ============================================================
REM  LIFE FIT - levanta todo con doble clic (Windows + XAMPP)
REM  1. Revisa que Apache/MySQL de XAMPP y LM Studio respondan
REM  2. Instala dependencias del frontend (solo la primera vez)
REM  3. Abre el navegador y arranca la app
REM ============================================================
cd /d "%~dp0"

echo.
echo  == LIFE FIT ==
echo.

curl -s -o nul -w "%%{http_code}" http://localhost/backend/Api/Auth/Consentimiento.php > "%TEMP%\lifefit_back.txt" 2>nul
set /p BACK=<"%TEMP%\lifefit_back.txt"
if "%BACK%"=="401" (
  echo  [OK]  Backend PHP en http://localhost/backend
) else (
  echo  [!!]  No responde el backend en http://localhost/backend/Api
  echo        Abre XAMPP, prende Apache y MySQL, y revisa que la carpeta se llame "backend" dentro de htdocs.
)

curl -s -o nul -w "%%{http_code}" http://localhost:1234/v1/models > "%TEMP%\lifefit_llm.txt" 2>nul
set /p LLM=<"%TEMP%\lifefit_llm.txt"
if "%LLM%"=="200" (
  echo  [OK]  LM Studio en el puerto 1234
) else (
  echo  [!!]  LM Studio no responde: la app funciona, pero sin IA. En LM Studio: Developer ^> Start Server.
)
echo.

cd frontend
if not exist node_modules (
  echo  Instalando dependencias, solo la primera vez...
  call npm install || (echo  Fallo npm install. ¿Tienes Node.js 20 o mas? & pause & exit /b 1)
)

start "" http://localhost:5173
call npm run dev
