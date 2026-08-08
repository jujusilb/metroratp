SELECT id, label FROM service;

SELECT l.label AS ligne, COUNT(DISTINCT s.id) AS nb_troncons, COUNT(DISTINCT dir.id) AS nb_directions, COUNT(m.id) AS nb_missions
FROM ligne l
JOIN desserte d ON d.ligne_id = l.id
JOIN troncon_desserte td ON td.desserte_id = d.id
JOIN troncon s ON s.id = td.troncon_id
LEFT JOIN mission m ON m.troncon_desserte_id = td.id
LEFT JOIN direction dir ON dir.ligne_id = l.id
GROUP BY l.id, l.label
ORDER BY nb_troncons ASC;
