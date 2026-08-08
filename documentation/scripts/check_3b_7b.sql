SELECT l.label AS ligne, l.id AS ligne_id,
    COUNT(DISTINCT d.id) AS nb_dessertes,
    COUNT(DISTINCT d.station_id) AS nb_stations
FROM ligne l
LEFT JOIN desserte d ON d.ligne_id = l.id
WHERE l.label IN ('3b', '7b')
GROUP BY l.id, l.label;
