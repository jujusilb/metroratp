SELECT t.id AS troncon_id, s.label AS station, tdT.label AS role
FROM troncon_desserte td
JOIN troncon t ON t.id = td.troncon_id
JOIN desserte d ON d.id = td.desserte_id
JOIN station s ON s.id = d.station_id
JOIN type_desserte tdT ON tdT.id = td.type_desserte_id
JOIN ligne l ON l.id = d.ligne_id
WHERE l.label = '10'
ORDER BY t.id, tdT.label;
