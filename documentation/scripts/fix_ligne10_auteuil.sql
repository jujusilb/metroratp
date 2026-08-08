START TRANSACTION;

-- 1. Supprimer le troncon 262 (Porte d'Auteuil <-> Mirabeau), mal cable
DELETE FROM mission WHERE troncon_desserte_id IN (SELECT id FROM troncon_desserte WHERE troncon_id=262);
DELETE FROM troncon_desserte WHERE troncon_id=262;
DELETE FROM troncon WHERE id=262;

-- 2. Les missions "vers Gare d'Austerlitz" sur 258-261 empruntaient le mauvais trace (celui de Boulogne)
DELETE FROM mission WHERE id IN (312,313,314,315);

-- 3. Rendre 258-261 a sens unique (vers Boulogne uniquement), en gardant Depart=cote eloigne / Arrivee=cote Boulogne
DELETE FROM troncon_desserte WHERE troncon_id=258 AND desserte_id=267 AND type_desserte_id=1;
DELETE FROM troncon_desserte WHERE troncon_id=258 AND desserte_id=268 AND type_desserte_id=2;
DELETE FROM troncon_desserte WHERE troncon_id=259 AND desserte_id=268 AND type_desserte_id=1;
DELETE FROM troncon_desserte WHERE troncon_id=259 AND desserte_id=269 AND type_desserte_id=2;
DELETE FROM troncon_desserte WHERE troncon_id=260 AND desserte_id=269 AND type_desserte_id=1;
DELETE FROM troncon_desserte WHERE troncon_id=260 AND desserte_id=270 AND type_desserte_id=2;
DELETE FROM troncon_desserte WHERE troncon_id=261 AND desserte_id=270 AND type_desserte_id=1;
DELETE FROM troncon_desserte WHERE troncon_id=261 AND desserte_id=271 AND type_desserte_id=2;

-- 4. Recabler le troncon 263 : Porte d'Auteuil <-> Javel devient Mirabeau <-> Javel (point de fusion des 2 branches)
UPDATE troncon_desserte SET desserte_id=271 WHERE troncon_id=263 AND desserte_id=274;
DELETE FROM mission WHERE id IN (317,763);

-- 5. Nouveaux troncons a sens unique pour la branche "vers Gare d'Austerlitz" : Boulogne -> Porte d'Auteuil -> Michel-Ange-Auteuil -> Mirabeau
INSERT INTO troncon (distance, duree_reelle_secondes, type_troncon_id) VALUES (NULL, NULL, 1);
SET @t_boulogne_porte = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_boulogne_porte, 267, 1);
SET @td_boulogne_porte_depart = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_boulogne_porte, 274, 2);

INSERT INTO troncon (distance, duree_reelle_secondes, type_troncon_id) VALUES (NULL, NULL, 1);
SET @t_porte_michelange = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_porte_michelange, 274, 1);
SET @td_porte_michelange_depart = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_porte_michelange, 273, 2);

INSERT INTO troncon (distance, duree_reelle_secondes, type_troncon_id) VALUES (NULL, NULL, 1);
SET @t_michelange_mirabeau = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_michelange_mirabeau, 273, 1);
SET @td_michelange_mirabeau_depart = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_michelange_mirabeau, 271, 2);

-- 6. Antenne Eglise d'Auteuil : impasse bidirectionnelle depuis Michel-Ange-Auteuil
INSERT INTO troncon (distance, duree_reelle_secondes, type_troncon_id) VALUES (NULL, NULL, 1);
SET @t_eglise = LAST_INSERT_ID();
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_eglise, 273, 1);
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_eglise, 272, 2);
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_eglise, 272, 1);
INSERT INTO troncon_desserte (troncon_id, desserte_id, type_desserte_id) VALUES (@t_eglise, 273, 2);

-- 7. Missions : branche vers Austerlitz (numero 1-3), + troncon 263 recable (numero 6, les 2 sens)
INSERT INTO mission (numero, service_id, troncon_desserte_id, direction_id)
VALUES (1, 1, @td_boulogne_porte_depart, 21);
INSERT INTO mission (numero, service_id, troncon_desserte_id, direction_id)
VALUES (2, 1, @td_porte_michelange_depart, 21);
INSERT INTO mission (numero, service_id, troncon_desserte_id, direction_id)
VALUES (3, 1, @td_michelange_mirabeau_depart, 21);

INSERT INTO mission (numero, service_id, troncon_desserte_id, direction_id)
SELECT 6, 1, td.id, 21 FROM troncon_desserte td WHERE td.troncon_id=263 AND td.desserte_id=271 AND td.type_desserte_id=1;
INSERT INTO mission (numero, service_id, troncon_desserte_id, direction_id)
SELECT 6, 1, td.id, 20 FROM troncon_desserte td WHERE td.troncon_id=263 AND td.desserte_id=275 AND td.type_desserte_id=1;

COMMIT;
