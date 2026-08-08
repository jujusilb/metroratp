SELECT
    td.troncon_id,
    td.id AS troncon_desserte_id,
    ty.label AS role,
    d.id AS desserte_id,
    l.label AS ligne,
    s.label AS station
FROM troncon_desserte td
JOIN type_desserte ty ON ty.id = td.type_desserte_id
JOIN desserte d ON d.id = td.desserte_id
JOIN station s ON s.id = d.station_id
JOIN ligne l ON l.id = d.ligne_id
WHERE td.troncon_id IN (197, 242)
ORDER BY td.troncon_id, ty.label;
