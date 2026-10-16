# SONI — Teste de Ouvido 01: Identificação de Frequências

**Situação:** código integrado à branch `main` após CI de PR aprovado; **não importado no banco local ou de produção**, **não publicado** e ainda sem homologação auditiva do responsável. Não altera rotas, frontend, esquema SQL nem o MVP 0.9.0 atual.

## Objetivo didático

Treinar o reconhecimento da altura de tons puros e a distinção entre sons graves, médios e agudos. Isto é treinamento de percepção auditiva, **não é audiometria**, teste clínico ou diagnóstico de perda auditiva.

A divisão aproximada adotada no exercício é:
- Graves: abaixo de aproximadamente 250 Hz;
- Médios: aproximadamente 250 Hz a 2 kHz;
- Agudos: acima de aproximadamente 2 kHz.

As divisões não são universais. Em áudio musical, as bandas frequentemente se subdividem em subgrave, grave, médio-grave, médio, médio-alto, presença e brilho.

## Conteúdo entregue

- 12 questões de escolha única com 3 opções cada.
- Questões 1–6: ouvir uma amostra e classificar a região.
- Questões 7–12: ouvir três amostras nas próprias opções e comparar grave/agudo/médio.
- 11 arquivos WAV distintos, reutilizados nas 12 questões para economizar armazenamento.
- Cada tom tem 2 segundos, é mono PCM 16-bit / 44,1 kHz, com fade-in/fade-out de 40 ms e pico próximo de -22 dBFS.
- Explicações por questão e devolutivas específicas por faixa de nota.
- Correção, tentativas e histórico usam o módulo existente de quizzes em PHP/MySQL: o gabarito fica no servidor até finalizar.
- A categoria "Testes de Ouvido" será criada se não existir.
- Rascunho por padrão: só aparece na área pública depois de o administrador publicá-lo.

## Requisitos

1. API SONI e MySQL/MariaDB configurados e acessíveis.
2. Migrações 001, 002 e 003 previamente executadas (já aplicadas no MVP em produção).
3. Dependências instaladas em `api/vendor` e `api/.env` configurado. **Não compartilhar o .env**.
4. Pasta `api/storage/site_media` gravável pelo usuário do comando.
5. Teste auditivo preferencialmente com fones de ouvido, em volume inicialmente baixo, sem tentar compensar frequências difíceis elevando muito o volume.

## Importação — ambiente local Windows

Após atualizar o repositório local com `git pull origin main`:

```powershell
cd api
php -l bin/seed-ear-frequency-01.php
php bin/seed-ear-frequency-01.php
```

O comando apresenta o ID do teste criado. Em seguida:

1. Abra `http://localhost:5173/admin/quizzes`.
2. Revise as 12 questões, as opções com áudio e as devolutivas.
3. Publique o teste pelo painel.
4. Abra `http://localhost:5173/testes`, entre na categoria **Testes de Ouvido** e realize uma tentativa completa.
5. Confirme a reprodução de todos os áudios, nota, explicações e devolutiva ao finalizar.

Opcionalmente, a flag `--publish` insere o teste já publicado; **não é a opção recomendada antes de validar no navegador**.

## Importação — servidor de produção

**Não há publicação automática por GitHub, pull request ou git pull.** Somente depois de validar localmente, atualizar o checkout e obter aprovação de publicação, execute na pasta `/var/www/somdaigreja.com.br/api`:

```bash
php -l bin/seed-ear-frequency-01.php
# Execute com a conta que possui acesso ao api/.env e permissão de escrita em storage/site_media:
php bin/seed-ear-frequency-01.php
```

O comando cria o teste apenas em **rascunho**, sem interferir nos testes existentes. Após conferir no painel, publique manualmente. Nunca execute contra o banco de outro projeto.

## Características operacionais e limites

- **Idempotência conservadora:** se houver um teste com o mesmo título, o comando recusa duplicação e não modifica dados já registrados. Editar/publicar pelo painel após a primeira execução.
- Falhas durante a transação removem os arquivos WAV recém-gerados e desfazem inserções de banco. Arquivos e registros preexistentes não são excluídos.
- Os áudios têm amplitude digital semelhante, mas isso **não garante sonoridade percebida idêntica** entre frequências nem em fones/caixas diferentes.
- A saída física depende da resposta de frequência do dispositivo; fones razoáveis são preferíveis a alto-falantes pequenos de celular.
- O teste é de *altura sonora* com tons puros. Timbres complexos e equalização de voz/instrumentos serão abordados em testes posteriores.
- Os 12 itens não são randomizados e as tentativas continuam anônimas, conforme o módulo Quiz existente.
- Mudanças desse teste após tentativas exigem criar outra versão, respeitando as restrições do sistema.
- **Não gera áudio no navegador:** os WAVs são arquivos locais criados pelo script e servidos pela API existente; sem dependência de plataformas externas.
