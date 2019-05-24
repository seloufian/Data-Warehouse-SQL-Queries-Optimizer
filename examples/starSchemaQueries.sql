select v.datevente, p.ref
	from ventes v, produit p
		where v.pid=p.pid
			and v.pid between 0 and 15;


select count(*), c.nom, p.ref
	from client c, ventes v, produit p
		where v.cid=c.cid and v.pid=p.pid
			and v.pid between 0 and 15
		group by c.nom, p.ref order by c.nom;


SELECT avg(c.age), v.prix, c.nom, v.datevente
	from ventes v, client c
		where c.cid=v.cid
			and prix between 7500 and 23000
			and c.age between 25 and 50
		group by v.prix, c.nom, v.datevente;


select v.mid, c.sexe
	from ventes v, client c
		where v.cid=c.cid
			and c.nom like 'A%'
			and c.age > 35
			and v.datevente <= '01-01-2018';


select min(c.age), v.prix, c.nom
	from ventes v, client c
		where v.cid=c.cid
			and prix between 7500 and 23000
			and c.age between 25 and 50
		group by v.prix, c.nom;


select max(v.datevente), m.region
	from ventes v, magasin m
		where v.mid=m.mid
			and m.surface>=150
			and v.prix<30000
	group by m.region;


select min(p.poids), p.gamme, c.nom
	from produit p, ventes v, client c
		where p.pid=v.vid
		and v.cid=c.cid
	group by p.gamme, c.nom
	order by c.nom;


select max(m.surface), c.ville
	from magasin m, client c, ventes v
		where m.mid=v.mid
		and c.cid=v.cid
		and m.surface>=150
		and v.prix<30000
	group by c.ville
	order by c.ville;


select sum(p.poids), c.nom
	from produit p, ventes v, client c
		where p.pid=v.vid
		and v.cid=c.cid
	group by c.nom;


select *
	from ventes v, magasin m, produit p
		where v.mid=m.mid
		and v.datevente between '02-05-2016' AND '02-05-2017'
		and m.surface<250
		and m.region='ALGER'
		and p.poids<135;
