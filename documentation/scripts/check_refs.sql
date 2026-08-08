SELECT 'ligne' t, id, label, couleur FROM ligne WHERE label IN ('3','3b','7','7b','11','5');
SELECT 'service' t, id, label, NULL FROM service;
SELECT 'type_desserte' t, id, label, NULL FROM type_desserte;
SELECT 'type_troncon' t, id, label, NULL FROM type_troncon;
SELECT 'style_station' t, id, label, NULL FROM style_station;
SELECT 'materiel' t, id, label, annee_production FROM materiel ORDER BY id LIMIT 30;
