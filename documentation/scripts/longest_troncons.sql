SELECT id, distance, ligne, depart, arrivee FROM (
    SELECT
    	t.id,
    	t.distance,
    	lA.label AS ligne,
    	sA.label AS depart,
    	sB.label AS arrivee,
        ROW_NUMBER() OVER (PARTITION BY t.id ORDER BY tdA.id) AS rn
    FROM
    	troncon t
    JOIN
    	troncon_desserte tdA ON tdA.troncon_id = t.id
    JOIN
    	type_desserte tyA ON tyA.id = tdA.type_desserte_id AND tyA.label = 'Départ'
    JOIN
    	desserte dA ON dA.id = tdA.desserte_id
    JOIN
    	station sA ON sA.id = dA.station_id
    JOIN
    	ligne lA ON lA.id = dA.ligne_id
    JOIN
    	troncon_desserte tdB ON tdB.troncon_id = t.id
    JOIN
    	type_desserte tyB ON tyB.id = tdB.type_desserte_id AND tyB.label = 'Arrivée'
    JOIN
    	desserte dB ON dB.id = tdB.desserte_id
    JOIN
    	station sB ON sB.id = dB.station_id
    WHERE
    	t.distance IS NOT NULL
    	AND sA.id <> sB.id
) x
WHERE
	rn = 1
ORDER BY distance DESC
LIMIT 15;
