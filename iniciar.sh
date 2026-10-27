#!/usr/bin/env sh
# LIFE FIT - levanta el frontend con un comando (Mac/Linux). El backend corre en XAMPP/Apache.
cd "$(dirname "$0")" || exit 1
[ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/backend/Api/Auth/Consentimiento.php)" = "401" ] \
  && echo "[OK] Backend PHP" || echo "[!!] No responde http://localhost/backend/Api (¿Apache/MySQL prendidos?)"
[ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:1234/v1/models)" = "200" ] \
  && echo "[OK] LM Studio" || echo "[!!] LM Studio no responde: la app funciona, pero sin IA"
cd frontend || exit 1
[ -d node_modules ] || npm install || exit 1
npm run dev
