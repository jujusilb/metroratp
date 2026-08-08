SELECT l.label AS ligne, m.label AS materiel, ml.arrivee, ml.fin
FROM materiel_ligne ml
JOIN ligne l ON l.id = ml.ligne_id
JOIN materiel m ON m.id = ml.materiel_id
WHERE l.label IN ('3','7','11')
ORDER BY l.label, ml.arrivee;

SELECT tt.label, COUNT(*) FROM troncon t JOIN type_troncon tt ON tt.id = t.type_troncon_id GROUP BY tt.label;
