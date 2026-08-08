SELECT svc.label AS service, dirStation.label AS direction_terminus, COUNT(*) AS nb
FROM mission m
JOIN troncon_desserte td ON td.id = m.troncon_desserte_id
JOIN desserte d ON d.id = td.desserte_id
JOIN ligne l ON l.id = d.ligne_id
JOIN direction dir ON dir.id = m.direction_id
JOIN desserte dirDesserte ON dirDesserte.id = dir.desserte_terminus_id
JOIN station dirStation ON dirStation.id = dirDesserte.station_id
JOIN service svc ON svc.id = m.service_id
WHERE l.label = '11'
GROUP BY svc.label, dirStation.label;
