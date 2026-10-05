-- Atualiza um banco criado antes da situação por vínculo (commit 9a56602 ou anterior).
-- 1. Cria a coluna e copia a situação geral da AR para cada vínculo, para não marcar
--    como "credenciado" um vínculo de AR que estava em credenciamento.
-- 2. Depois, reimporte o structure.json (tela /importar ou composer importar-estrutura):
--    a importação ajusta a situação dos vínculos que diferem no arquivo do ITI.
ALTER TABLE ar_ac_n2 ADD situacao SMALLINT DEFAULT 4002 NOT NULL;
UPDATE ar_ac_n2 v JOIN ar r ON r.id = v.ar_id SET v.situacao = r.situacao;
