SELECT
    m.id, m.numero,
    t.id AS troncon_id,
    sDep.label AS depart_station,
    dirStation.label AS direction_terminus,
    svc.label AS service
FROM mission m
JOIN troncon_desserte td ON td.id = m.troncon_desserte_id
JOIN troncon t ON t.id = td.troncon_id
JOIN desserte d ON d.id = td.desserte_id
JOIN station sDep ON sDep.id = d.station_id
JOIN ligne l ON l.id = d.ligne_id
JOIN direction dir ON dir.id = m.direction_id
JOIN desserte dirDesserte ON dirDesserte.id = dir.desserte_terminus_id
JOIN station dirStation ON dirStation.id = dirDesserte.station_id
JOIN service svc ON svc.id = m.service_id
WHERE l.label = '11'
ORDER BY dirStation.label, svc.label, m.numero
LIMIT 40;
