INSERT INTO type_transport (label) VALUES ('Bus'), ('Car'), ('RER'), ('Métro'), ('Tramway');
INSERT INTO gestionnaire (label) VALUES ('RATP'), ('SNCF'), ('Keolis');

UPDATE ligne l
SET type_transport_id = (SELECT id FROM type_transport WHERE label = 'Métro'),
    gestionnaire_id = (SELECT id FROM gestionnaire WHERE label = 'RATP')
WHERE l.label IN ('1','2','3','3b','4','5','6','7','7b','8','9','10','11','12','13','14');
