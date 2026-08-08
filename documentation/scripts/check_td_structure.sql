SELECT td.id, td.troncon_id, td.desserte_id, s.label AS station, tdT.label AS role
FROM troncon_desserte td
JOIN desserte d ON d.id = td.desserte_id
JOIN station s ON s.id = d.station_id
JOIN type_desserte tdT ON tdT.id = td.type_desserte_id
JOIN ligne l ON l.id = d.ligne_id
WHERE l.label = '11'
ORDER BY td.troncon_id, td.id
LIMIT 20;
