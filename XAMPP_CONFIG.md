# Configuração para XAMPP (Windows)

## Limites de Upload
O arquivo `.user.ini` **não funciona no XAMPP**. 
Edite diretamente o `php.ini` do XAMPP:

1. Abra o **XAMPP Control Panel**
2. Clique em **Config** ao lado do Apache → escolha **PHP (php.ini)**
3. Encontre e altere estas linhas:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 60
```

4. Salve o arquivo
5. No XAMPP Control Panel: clique **Stop** e depois **Start** no Apache

## Banco de Dados
- Usuário padrão: `root` (sem senha)
- Edite `includes/config.php`: `DB_USER = 'root'`, `DB_PASS = ''`

## URL Base
Se a pasta se chamar `intranet-acqua`, acesse:
`http://localhost/intranet-acqua/`

## Permissões de Upload
No Windows o XAMPP já tem permissão para gravar em `htdocs/`.
Se der erro de permissão: clique com botão direito na pasta `uploads/`
→ Propriedades → Segurança → dar controle total ao usuário atual.
