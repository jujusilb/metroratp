SELECT t.id AS troncon_id, s.label AS station, tdT.label AS role, t.distance, t.duree_reelle_secondes
FROM troncon_desserte td
JOIN troncon t ON t.id = td.troncon_id
JOIN desserte d ON d.id = td.desserte_id
JOIN station s ON s.id = d.station_id
JOIN type_desserte tdT ON tdT.id = td.type_desserte_id
WHERE t.id IN (
  SELECT DISTINCT td2.troncon_id FROM troncon_desserte td2
  JOIN desserte d2 ON d2.id = td2.desserte_id
  JOIN station s2 ON s2.id = d2.station_id
  WHERE s2.label = 'Liège'
)
ORDER BY t.id, tdT.label;
