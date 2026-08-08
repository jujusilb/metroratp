SELECT t.id AS troncon_id, s.label AS station, tdT.label AS role
FROM troncon_desserte td
JOIN troncon t ON t.id = td.troncon_id
JOIN desserte d ON d.id = td.desserte_id
JOIN station s ON s.id = d.station_id
JOIN type_desserte tdT ON tdT.id = td.type_desserte_id
JOIN ligne l ON l.id = d.ligne_id
WHERE l.label = '6'
ORDER BY t.id, tdT.label;

SELECT sA.label AS stationA, lA.label AS ligneA, sB.label AS stationB, lB.label AS ligneB
FROM correspondance c
JOIN desserte dA ON dA.id=c.desserte_a_id JOIN station sA ON sA.id=dA.station_id JOIN ligne lA ON lA.id=dA.ligne_id
JOIN desserte dB ON dB.id=c.desserte_b_id JOIN station sB ON sB.id=dB.station_id JOIN ligne lB ON lB.id=dB.ligne_id
WHERE lA.label='6' OR lB.label='6'
GROUP BY sA.label, lA.label, sB.label, lB.label
ORDER BY sA.label;
