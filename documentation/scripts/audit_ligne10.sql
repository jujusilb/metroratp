SELECT s.label AS station, d.id AS desserte_id, COUNT(DISTINCT td.troncon_id) AS nb_troncons
FROM desserte d
JOIN station s ON s.id = d.station_id
JOIN ligne l ON l.id = d.ligne_id
LEFT JOIN troncon_desserte td ON td.desserte_id = d.id
WHERE l.label = '10'
GROUP BY d.id
ORDER BY nb_troncons, station;
