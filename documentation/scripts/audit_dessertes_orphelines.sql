SELECT l.label AS ligne, s.label AS station, d.id AS desserte_id
FROM desserte d
JOIN station s ON s.id = d.station_id
JOIN ligne l ON l.id = d.ligne_id
LEFT JOIN troncon_desserte td ON td.desserte_id = d.id
WHERE td.id IS NULL
ORDER BY l.label, s.label;
