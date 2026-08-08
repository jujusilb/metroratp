SELECT ml.id, l.label, m.label, ml.arrivee, ml.fin
FROM materiel_ligne ml
JOIN ligne l ON l.id = ml.ligne_id
JOIN materiel m ON m.id = ml.materiel_id
WHERE l.label IN ('3b','7b')
ORDER BY ml.id;
