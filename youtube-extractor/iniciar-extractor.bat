@echo off
title YouTube SEO Extractor Server
:: Muda para o diretório correto (garante compatibilidade com partições diferentes)
cd /d "e:\xampp\htdocs\oscarlima\youtube-extractor"
echo ==========================================================
echo    Iniciando o servidor do YouTube SEO Extractor...
echo    Acesse: http://localhost:3000
echo ==========================================================
echo.
node server.js
if errorlevel 1 (
    echo.
    echo [ERRO] O servidor falhou ao iniciar ou fechou com erros.
    echo Verifique se o Node.js esta instalado e as dependencias carregadas.
    pause
)
