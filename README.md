# Intranet
**Versão 2.1** · PHP 8 + MySQL · Modular · Dark Mode

---

## 📁 Estrutura de Arquivos

```
intranet/
├── index.php              # Intranet principal (requer login)
├── login.php              # Tela de login
├── logout.php             # Encerrar sessão
├── public.php             # Área pública (sem login) — sistemas, comunicados, notícias
├── database.sql           # Schema + dados iniciais
├── migrate.php             # Aplica migrações de banco pendentes
├── migrations/             # Scripts .sql de cada mudança de schema
├── .htaccess              # Segurança Apache
│
├── includes/
│   ├── config.php         # ⚙️ Configurações gerais (URLs, sessão)
│   ├── db_config.php      # 🔑 Dados de conexão do banco ← EDITAR ANTES DE INSTALAR
│   ├── database.php       # Classe PDO singleton
│   ├── functions.php      # Helpers de auth, slug, etc.
│   ├── image.php          # Upload/resize/thumbnail de imagens
│   ├── header.php         # Navbar global
│   └── footer.php         # Rodapé global
│
├── admin/
│   ├── index.php          # Dashboard (TI + Comunicação)
│   ├── posts.php          # Editor de comunicados e notícias
│   ├── modules.php        # Sistemas e links rápidos (com upload de ícone)
│   ├── categories.php     # Categorias de publicações
│   ├── nav.php            # Menu de navegação configurável
│   ├── users.php          # Usuários (apenas Admin/TI)
│   └── settings.php       # Configurações gerais (apenas Admin/TI)
│
├── api/
│   ├── toggle_dark.php    # API: alternar modo escuro
│   └── sort_modules.php   # API: reordenar módulos (drag-and-drop)
│
├── assets/
│   ├── css/style.css      # Estilos: modo claro/escuro, imagens, cards
│   └── js/main.js         # Dark mode, editor, drag-sort, toasts
│
└── uploads/
    ├── posts/             # Imagens de capa dos posts
    └── modules/           # Ícones/logos dos sistemas
```

---

## 🚀 Instalação

### Requisitos
- PHP **8.0+** com extensões: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`
- MySQL **8.0+** ou MariaDB **10.4+**
- Apache com `mod_rewrite`

### Passo a passo

**1. Copie os arquivos**
```bash
cp -r intranet/ /var/www/html/intranet/
```

**2. Importe o banco de dados**
```bash
mysql -u root -p < database.sql
```

**3. Configure `includes/db_config.php`**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'intranet');
define('DB_USER', 'usuario_mysql');
define('DB_PASS', 'senha_mysql');
```
(`BASE_URL` em `includes/config.php` é detectada automaticamente — só descomente/ajuste se necessário)

**4. Permissões da pasta uploads**
```bash
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

**5. Rode as migrações pendentes**

Abra `http://seuservidor.com/intranet/migrate.php` logado como admin (ou `php migrate.php` via CLI).

**6. Acesse**
- Intranet: `http://seuservidor.com/intranet/`
- Área Pública: `http://seuservidor.com/intranet/public.php`
- Admin: `http://seuservidor.com/intranet/admin/`

---

## 🔑 Credenciais Iniciais

| Usuário | E-mail | Senha | Nível |
|---------|--------|-------|-------|
| Administrador | admin@example.com | admin123 | Admin (TI) |
| Equipe Comunicação | comunicacao@example.com | admin123 | Editor |

> ⚠️ **Altere as senhas imediatamente após o primeiro acesso!** As senhas acima são só o valor inicial do `database.sql` de exemplo.

---

## 👥 Níveis de Acesso

| Nível | Acesso |
|-------|--------|
| **Admin** (TI) | Tudo: usuários, configurações, módulos, posts |
| **Editor** (Comunicação) | Posts, módulos, categorias, menu |
| **Usuário** | Visualizar conteúdo (intranet) |

---

## 🌐 Área Pública (sem login)
A página `public.php` exibe:
- **Todos os sistemas institucionais** marcados como públicos
- Comunicados marcados como "público"
- Notícias marcadas como "público"
- Post em destaque (featured)
- Links rápidos públicos

---

## 🖼️ Imagens — Formatos Suportados

| Formato | Suporte |
|---------|---------|
| JPG / JPEG | ✅ Nativo |
| PNG (com transparência) | ✅ Nativo |
| GIF | ✅ Nativo |
| WEBP | ✅ Nativo |
| BMP | ✅ Convertido para JPG |
| TIFF | ✅ Convertido para JPG |

**Processamento automático:**
- Redimensionamento para máx. 1920×1080px (posts)
- Thumbnail automático 600×340px
- Qualidade JPEG: 85%
- Limite de upload: **15MB**

---

## 🧩 Sistemas Pré-configurados (exemplo)

O `database.sql` traz alguns módulos de exemplo com `is_public = 1` (visíveis sem login) — edite/apague em Admin → Módulos:

| Sistema | Descrição |
|---------|-----------|
| Chamados | Sistema de chamados de TI |
| Indicadores | Sistema de indicadores/BI |
| Laboratório | Resultados e exames |
| Webmail | E-mail institucional |

> **Configure as URLs reais** em Admin → Módulos → Sistemas.
> Você pode também fazer upload do **logotipo de cada sistema** como ícone.

---

## 🎨 Personalização

### Paleta de cores
```css
/* assets/css/style.css */
:root {
  --primary:      #00897B;  /* Ciano-esverdeado */
  --primary-dark: #00695C;  /* Verde médio */
  --accent2:      #004D40;  /* Verde água escuro */
}
```

### Modo Escuro
Ativado pelo botão na navbar. Salvo por: sessão PHP + banco de dados + cookie (30 dias).

---

## 🔄 Atualizando para uma versão nova

1. Substitua todos os arquivos do projeto **exceto** `includes/db_config.php` (mantém suas credenciais de banco) e a pasta `uploads/` (mantém as imagens já enviadas).
2. Abra `migrate.php` (navegador, logado como admin, ou `php migrate.php` via CLI) — ele aplica só as mudanças de banco que a versão nova trouxer.

---

## 🔒 Segurança
- Senhas com **bcrypt** (custo 12)
- **CSRF token** em todos os formulários
- Validação MIME real de imagens (não confia em extensão)
- Upload sanitizado com verificação via `getimagesize()`
- Sanitização de inputs com `strip_tags` + `htmlspecialchars`
- `.htaccess` bloqueia acesso direto a `/includes/`, `/backups/` e `/migrations/`
