# admin2fa_domain

Plugin para Roundcube Webmail que permite que usuários definidos como **admins de domínio** (via `config.inc.php`)
ativem ou desativem o 2FA (plugin [`twofactor_gauthenticator`](https://github.com/alexandregz/twofactor_gauthenticator))
de outros usuários — mas **somente usuários do mesmo domínio de e-mail** do admin logado.

<img width="1919" height="995" alt="image" src="https://github.com/user-attachments/assets/49e5a568-cdaa-4801-a4a6-97a9ec0ddd9e" />


## Instalação

1. Copie a pasta `admin2fa_domain` para `HOME_RC/plugins/`.
2. Copie `config.inc.php.dist` para `config.inc.php` dentro da pasta do plugin e ajuste a lista `admin2fa_domain_admins`.
3. Ative o plugin em `HOME_RC/config/config.inc.php`:

```php
$config['plugins'] = [
    // ...outros plugins...
    'twofactor_gauthenticator',
    'admin2fa_domain',
];
```

4. Certifique-se de que o plugin `twofactor_gauthenticator` já está instalado e funcionando — este plugin não o substitui, ele só lê/grava a mesma preferência.

Nenhuma tabela nova é criada. O plugin usa exclusivamente a tabela `users` (colunas `user_id`, `username`, `preferences`) que já existe no Roundcube.

## Como funciona

- Um usuário é considerado "admin" se o e-mail dele bater com algum item de `$config['admin2fa_domain_admins']`
(cada item é tratado como uma regex ancorada, no mesmo estilo do `users_allowed_2FA` do próprio twofactor_gauthenticator).

- O **domínio gerenciável** do admin é deduzido automaticamente do próprio e-mail dele (tudo depois do `@`). Um admin `suporte@clientex.com`
só enxerga e só pode alterar usuários `...@clientex.com`. Essa verificação é sempre refeita no servidor a cada ação — não depende do que o navegador manda.

- Ao logar, o admin vê um novo item em **Configurações**: "Administração da Autenticação 2FA", com uma tabela: e-mail, status (Ativo/Inativo) e um botão.

## Onde fica o status "ativo/desativado" (pesquisado no código-fonte)

No `twofactor_gauthenticator.php` (método privado `__get2FAconfig` / `__set2FAconfig`), a preferência não é um simples `1`/`0` na tabela
é um **array PHP guardado na chave `twofactor_gauthenticator`** dentro da coluna `preferences` (que o Roundcube serializa inteira, junto com todas
as outras preferências do usuário). Esse array tem, entre outras, as chaves:

```php
[
    'activate'        => true|false,   // é isso que liga/desliga o 2FA
    'secret'          => 'BASE32SECRET...',
    'recovery_codes'  => ['xxxx', 'yyyy', ...],
]
```

Detalhe importante encontrado no código: quando o **próprio usuário** desativa o 2FA pela tela de Configurações, o `twofactor_gauthenticator` **apaga o array inteiro**
(`secret` e `recovery_codes` inclusive) — não deixa só `activate = false`. O nosso plugin de admin, propositalmente, **não apaga o secret ao desativar**, só muda `activate` para `false`.
Isso é intencional (veja a seção de segurança abaixo).

Se `$config['twofactor_pref_encrypt']` estiver ativado no `twofactor_gauthenticator`, esse array vem criptografado com a chave `des_key` do Roundcube.
O plugin já trata os dois casos (com e sem criptografia), reproduzindo a mesma lógica de decodificação do plugin original.

## Por que "Ativar" nem sempre aparece (decisão de segurança)

O 2FA por TOTP só funciona se existir um `secret` já configurado — e o `secret` só é criado quando o **próprio usuário** configura o Google Authenticator pela tela de Configurações
escaneando o QR code). Não faz sentido, e é perigoso, um admin "ligar" o `activate` de alguém que nunca gerou um secret: no próximo login esse usuário seria obrigado a digitar
um código de 6 dígitos que **nunca vai conseguir gerar**, e ficaria travado para sempre fora da conta (sem nem recovery codes).

Por isso, o botão funciona assim:

| Situação do usuário                                    | O que aparece                         |
|--------------------------------------------------------|---------------------------------------|
| 2FA ativo (`activate = true`)                          | Botão **Desativar**                   |
| 2FA configurado antes, mas desativado (tem `secret`)   | Botão **Ativar**                      |
| Nunca configurou 2FA (sem `secret`)                    | Aviso "Não configurado" (sem botão)   |

- **Desativar**: sempre seguro, sempre disponível quando ativo. Só muda `activate` para `false` (mantém o `secret`, ao contrário do comportamento padrão do plugin original,
para permitir reativar depois sem o usuário precisar reconfigurar do zero).

- **Ativar**: só aparece quando já existe um `secret` salvo (ou seja, o usuário já passou pela configuração alguma vez e foi desativado por um admin). Nesse caso é seguro reativar,
porque o secret continua o mesmo que o usuário já tem no app autenticador dele.

- Se você realmente precisa **forçar** um usuário que nunca configurou a fazer o enrollment, o próprio `twofactor_gauthenticator` já tem a opção global
`$config['force_enrollment_users'] = true;` (obriga todo mundo a configurar no primeiro login).

## Paginação e rolagem

Para domínios com muitas contas, a listagem é paginada em blocos de **12 usuários por página** (constante `PAGE_SIZE` no topo da classe, caso queira mudar)
A tabela também fica dentro de uma área com rolagem vertical (`max-height: 65vh`) e cabeçalho fixo, então mesmo com muitas contas na mesma página a tela não fica gigante.
Ao ativar/desativar um usuário, a página recarrega mantendo a página atual da paginação.

## Log de auditoria

Toda ativação/desativação feita por um admin é gravada em `logs/admin2fa_domain` (via `rcube::write_log`), com quem fez, em quem, e de qual domínio — útil para auditoria.

## Estrutura de arquivos

```
admin2fa_domain/
├── admin2fa_domain.php          # plugin principal
├── admin2fa_domain.js           # ajax dos botoes ativar/desativar
├── config.inc.php.dist          # exemplo de configuracao
├── localization/
│   ├── pt_BR.inc
│   └── en_US.inc
└── skins/elastic/
    └── admin2fa_domain.css
```

Testado visualmente com o skin Elastic (padrão do Roundcube 1.5/1.6). Se você usar outro skin, pode ser necessário ajustar o CSS.
