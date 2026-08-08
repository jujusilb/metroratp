SELECT 'dessertes' t, l.label, COUNT(*) FROM desserte d JOIN ligne l ON l.id=d.ligne_id WHERE l.label IN ('3b','7b') GROUP BY l.label;
SELECT 'troncons' t, l.label, COUNT(DISTINCT td.troncon_id) FROM troncon_desserte td JOIN desserte d ON d.id=td.desserte_id JOIN ligne l ON l.id=d.ligne_id WHERE l.label IN ('3b','7b') GROUP BY l.label;
SELECT 'missions' t, l.label, COUNT(*) FROM mission m JOIN troncon_desserte td ON td.id=m.troncon_desserte_id JOIN desserte d ON d.id=td.desserte_id JOIN ligne l ON l.id=d.ligne_id WHERE l.label IN ('3b','7b') GROUP BY l.label;
SELECT 'directions' t, l.label, s.label FROM direction dir JOIN ligne l ON l.id=dir.ligne_id JOIN desserte d ON d.id=dir.desserte_terminus_id JOIN station s ON s.id=d.station_id WHERE l.label IN ('3b','7b');
SELECT 'materiel' t, l.label, m.label, ml.arrivee, ml.fin FROM materiel_ligne ml JOIN ligne l ON l.id=ml.ligne_id JOIN materiel m ON m.id=ml.materiel_id WHERE l.label IN ('3b','7b') ORDER BY l.label, ml.arrivee;
SELECT 'correspondances' t, sA.label, lA.label, sB.label, lB.label FROM correspondance c
  JOIN desserte dA ON dA.id=c.desserte_a_id JOIN station sA ON sA.id=dA.station_id JOIN ligne lA ON lA.id=dA.ligne_id
  JOIN desserte dB ON dB.id=c.desserte_b_id JOIN station sB ON sB.id=dB.station_id JOIN ligne lB ON lB.id=dB.ligne_id
  WHERE lA.label IN ('3b','7b') OR lB.label IN ('3b','7b');

-- Detail missions 7bis pour verifier la topologie asymetrique
SELECT m.numero, sDep.label AS depart, dirS.label AS direction, t.id AS troncon_id
FROM mission m
JOIN troncon_desserte td ON td.id = m.troncon_desserte_id
JOIN desserte dDep ON dDep.id = td.desserte_id
JOIN station sDep ON sDep.id = dDep.station_id
JOIN troncon t ON t.id = td.troncon_id
JOIN direction dir ON dir.id = m.direction_id
JOIN desserte dDir ON dDir.id = dir.desserte_terminus_id
JOIN station dirS ON dirS.id = dDir.station_id
JOIN ligne l ON l.id = dDep.ligne_id
WHERE l.label = '7b'
ORDER BY dirS.label, m.numero;
