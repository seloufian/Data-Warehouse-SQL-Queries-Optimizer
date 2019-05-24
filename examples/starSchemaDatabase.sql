/* Suppression de l'utilisateur PFE, au cas où il est déjà créé */
DROP USER PFE CASCADE;


/* Allouer 512MB de tablespace par défaut supplémentaire */
ALTER TABLESPACE SYSTEM ADD DATAFILE 'C:/oraclexe/DFSYS/extend_SysTabSpace.dbf' SIZE 512M;


CREATE USER PFE IDENTIFIED BY PFE;
GRANT ALL PRIVILEGES TO PFE;


connect PFE / PFE


/* Création de table PRODUIT */
CREATE TABLE PRODUIT (
	PID NUMBER(5) PRIMARY KEY,
	REF VARCHAR2(5) NOT NULL UNIQUE,
	GAMME VARCHAR2(20) NOT NULL,
	POIDS NUMBER(4) NOT NULL
);


/* Création de table CLIENT */
CREATE TABLE CLIENT (
	CID NUMBER(7) PRIMARY KEY,
	NOM VARCHAR2(20) NOT NULL UNIQUE,
	SEXE VARCHAR2(1) NOT NULL,
	VILLE VARCHAR2(15) NOT NULL,
	AGE VARCHAR2(2) NOT NULL
);


/* Création de table MAGASIN */
CREATE TABLE MAGASIN (
	MID NUMBER(2) PRIMARY KEY,
	SURFACE NUMBER(3) NOT NULL,
	REGION VARCHAR2(15) NOT NULL
);


/* Création de table VENTES */
CREATE TABLE VENTES (
	VID NUMBER(8) PRIMARY KEY,
	PID NUMBER(7) REFERENCES PRODUIT,
	CID NUMBER(7) REFERENCES CLIENT,
	MID NUMBER(2) REFERENCES MAGASIN,
	PRIX NUMBER(5) NOT NULL,
	DATEVENTE DATE NOT NULL
);



/* Insertion des tuples de PRODUIT */
DECLARE
	i NUMBER := 0;
	ref VARCHAR2(5);
	gamme VARCHAR2(20);
BEGIN
	FOR a1 IN 65..90 LOOP
		FOR a2 IN 65..90 LOOP
			FOR a3 IN 65..90 LOOP
				ref := chr(a1)||chr(a2)||chr(a3);
				gamme := chr(a1)||chr(a3)||chr(a2)||chr(a3)||chr(a1)||chr(a1)||chr(a2)||chr(a3)||chr(a2);

				INSERT INTO PRODUIT VALUES(i, ref, gamme, trunc(dbms_random.value(100,9999)));

				i := i+1;
			IF i = 7000 THEN EXIT; END IF;
			END LOOP;
		IF i = 7000 THEN EXIT; END IF;
		END LOOP;
	IF i = 7000 THEN EXIT; END IF;
	END LOOP;

	COMMIT;
END;
/



/* Insertion des tuples de CLIENT */
DECLARE
	i NUMBER := 0;
	nom VARCHAR2(6);
	ville VARCHAR2(10);
	sexe VARCHAR2(1) := 'M';
BEGIN
	FOR a1 IN 65..90 LOOP
		FOR a2 IN 65..90 LOOP
			FOR a3 IN 65..90 LOOP
				FOR a4 IN 65..90 LOOP
					nom := chr(a1)||chr(a2)||chr(a3)||chr(a4);
					ville := chr(a3)||chr(a2)||chr(a1)||chr(a3)||chr(a4)||chr(a2)||chr(a4);

					INSERT INTO CLIENT VALUES(i, nom, sexe, ville, trunc(dbms_random.value(20,75)));

					IF sexe = 'M' THEN sexe := 'F';
						ELSE sexe := 'M';
					END IF;
					i := i+1;
				IF i = 150000 THEN EXIT; END IF;
				END LOOP;
			IF i = 150000 THEN EXIT; END IF;
			END LOOP;
		IF i = 150000 THEN EXIT; END IF;
		END LOOP;
	IF i = 150000 THEN EXIT; END IF;
	END LOOP;

	COMMIT;
END;
/



/* Insertion des tuples de MAGASIN */
BEGIN
	FOR i IN 0..49 LOOP
		INSERT INTO MAGASIN VALUES(i, trunc(dbms_random.value(100,999)), 'ALGER');
	END LOOP;

	COMMIT;
END;
/



/* Insertion des tuples de VENTES */
DECLARE
	pid NUMBER := 0;
	cid NUMBER := 0;
	mid NUMBER := 0;
	prix NUMBER(5);
	datev VARCHAR2(10);
BEGIN
	FOR vid IN 0..1999999 LOOP
		prix := trunc(dbms_random.value(100,99999));
		datev := trunc(dbms_random.value(1,28)) || '-' || trunc(dbms_random.value(1,12)) || '-' || trunc(dbms_random.value(2013, 2018));

		INSERT INTO VENTES VALUES(vid, pid, cid, mid, prix, datev);

		pid := pid+1;
		cid := cid+1;
		mid := mid+1;

		IF pid = 7000 THEN pid := 0; END IF;
		IF cid = 150000 THEN cid := 0; END IF;
		IF mid = 50 THEN mid := 0; END IF;
	END LOOP;

	COMMIT;
END;
/
