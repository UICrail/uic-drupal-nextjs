# Reference des raccourcis typographiques SPIP

Ce document recense les raccourcis typographiques utilises par SPIP (spip.net) et leur equivalent HTML. Cette reference est utile lors de la migration de contenus SPIP vers Drupal 10, pour convertir le balisage SPIP en HTML standard.

> **Source** : [Documentation officielle SPIP](https://www.spip.net/en_article847.html)

## Table des matieres

- [Resume rapide](#resume-rapide)
- [Mise en forme du texte](#mise-en-forme-du-texte)
- [Paragraphes et retours a la ligne](#paragraphes-et-retours-a-la-ligne)
- [Intertitres](#intertitres)
- [Ligne de separation](#ligne-de-separation)
- [Liens hypertextes](#liens-hypertextes)
- [Liens internes SPIP](#liens-internes-spip)
- [Notes de bas de page](#notes-de-bas-de-page)
- [Citations](#citations)
- [Listes](#listes)
- [Tableaux](#tableaux)
- [Glossaire externe](#glossaire-externe)
- [Ancres nommees](#ancres-nommees)
- [Code source](#code-source)
- [Desactivation des raccourcis](#desactivation-des-raccourcis)
- [Typographie automatique francaise](#typographie-automatique-francaise)
- [Table de conversion complete](#table-de-conversion-complete)

---

## Resume rapide

```
{{{Un intertitre}}}

Texte avec {italique} et {{gras}} ou un {{ {melange des deux} }}.
Un retour a la ligne avec [un lien externe->https://www.spip.net]
et [un lien interne->art12] avec un titre automatique [->rubrique10].

Un autre paragraphe avec [[Une note de bas de page]] et un terme de [?glossaire].

-* Une liste
-* Une liste
-** Un sous-element de liste

-# Une liste ordonnee
-# Une liste ordonnee
-## Sous-element d'une liste ordonnee

----

| {{ Lieu }} | {{ Action }} |
| Caves | Spip Party |
| Toulouse | Spip en rose |

[ancre<-] definition d'une ancre avec [un lien pour y aller->ancre].

<quote>Une citation</quote>
```

---

## Mise en forme du texte

| Raccourci SPIP | Rendu HTML | Description |
|----------------|------------|-------------|
| `{texte}` | `<em>texte</em>` | Italique |
| `{{texte}}` | `<strong>texte</strong>` | Gras |
| `{{{titre}}}` | `<h3>titre</h3>` | Intertitre (titre de section) |
| `{{ {texte} }}` | `<strong><em>texte</em></strong>` | Gras + italique |

### Exemples

| SPIP | Rendu |
|------|-------|
| `Texte en {italique}` | Texte en *italique* |
| `Texte en {{gras}}` | Texte en **gras** |
| `{{{Section importante}}}` | **Section importante** (titre centre) |

---

## Paragraphes et retours a la ligne

| Action | Syntaxe SPIP | Equivalent HTML |
|--------|-------------|----------------|
| Nouveau paragraphe | Ligne vide (sauter une ligne) | `<p>...</p>` |
| Retour a la ligne simple | `_` en debut de ligne + espace | `<br />` |

> **Attention** : Un simple retour a la ligne (Entree) dans SPIP ne genere pas de `<br>`. Il faut une ligne vide pour un nouveau paragraphe ou `_` pour un retour a la ligne.

Plusieurs lignes vides consecutives n'ont aucun effet supplementaire (elles sont reduites a un seul saut de paragraphe).

---

## Intertitres

Les intertitres sont des titres a l'interieur d'un texte qui montrent sa structure.

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `{{{Titre de section}}}` | `<h3 class="spip">Titre de section</h3>` |

Le titre est affiche en gras et centre par defaut dans SPIP.

---

## Ligne de separation

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `----` (au moins 4 tirets sur une ligne seule) | `<hr />` |

---

## Liens hypertextes

### Liens externes

| Raccourci SPIP | Equivalent HTML | Description |
|----------------|----------------|-------------|
| `[texte->url]` | `<a href="url">texte</a>` | Lien avec texte personnalise |
| `[->url]` | `<a href="url">url</a>` | Lien avec affichage de l'URL |
| `[texte->mailto:email@example.com]` | `<a href="mailto:...">texte</a>` | Lien email |
| `[texte->ftp://...]` | `<a href="ftp://...">texte</a>` | Lien FTP |

### Liens avec infobulle

```
[texte visible|Texte de l'infobulle->http://www.example.com]
```

Genere un lien avec un attribut `title` qui s'affiche au survol de la souris.

### Liens avec langue cible

```
[Un site en francais{fr}->http://www.example.com]
```

Ajoute un attribut `hreflang` au lien.

### Exemples

| SPIP | HTML genere |
|------|------------|
| `[SPIP->http://www.spip.net]` | `<a href="http://www.spip.net">SPIP</a>` |
| `[->http://www.spip.net]` | `<a href="http://www.spip.net">http://www.spip.net</a>` |

---

## Liens internes SPIP

SPIP dispose d'un systeme de liens internes base sur les identifiants numeriques des contenus.

### Syntaxe des liens internes

| Raccourci SPIP | Cible | Description |
|----------------|-------|-------------|
| `[texte->342]` | Article 342 | Lien vers un article (numero seul) |
| `[texte->art342]` | Article 342 | Lien vers un article (prefixe `art`) |
| `[texte->article 342]` | Article 342 | Lien vers un article (mot complet) |
| `[->art342]` | Article 342 | Affiche automatiquement le titre de l'article |
| `[texte->rub12]` | Rubrique 12 | Lien vers une rubrique |
| `[texte->rubrique 12]` | Rubrique 12 | Lien vers une rubrique (mot complet) |
| `[texte->br65]` | Breve 65 | Lien vers une breve |
| `[texte->breve 65]` | Breve 65 | Lien vers une breve (mot complet) |
| `[texte->aut13]` | Auteur 13 | Lien vers un auteur |
| `[texte->auteur13]` | Auteur 13 | Lien vers un auteur (mot complet) |
| `[texte->mot32]` | Mot-cle 32 | Lien vers un mot-cle |
| `[texte->site1]` | Site syndique 1 | Lien vers un site syndique |
| `[texte->doc17]` | Document 17 | Lien vers un document attache |
| `[texte->document17]` | Document 17 | Lien vers un document (mot complet) |
| `[texte->img13]` | Image 13 | Lien vers une image |
| `[texte->image13]` | Image 13 | Lien vers une image (mot complet) |

> **Note pour la migration** : Ces liens internes doivent etre convertis en URLs Drupal lors de la migration. Le champ `field_spip_id` permet de retrouver la correspondance entre les IDs SPIP et les noeuds Drupal.

---

## Notes de bas de page

### Notes automatiques

| Raccourci SPIP | Description |
|----------------|-------------|
| `Texte[[Contenu de la note]]` | Note de bas de page numerotee automatiquement |

SPIP gere automatiquement la numerotation et les liens hypertextes entre le texte et les notes.

### Notes manuelles (avancees)

| Raccourci SPIP | Description |
|----------------|-------------|
| `[[<23> Texte de la note]]` | Note avec numero impose (23) |
| `[[<*> Texte de la note]]` | Note avec asterisque |
| `[[<> Texte de la note]]` | Note sans lien visible dans le texte |
| `[[<Ref> Texte de la note]]` | Note avec reference nommee (ex: "Ref") |
| `[[<23>]]` | Lien vers une note existante (numero 23) |

### Exemples

| SPIP | Rendu |
|------|-------|
| `Un texte[[Explication supplementaire.]]` | Un texte [1] ... [1] Explication supplementaire. |
| `Un texte[[<*> Note avec asterisque]]` | Un texte [*] ... [*] Note avec asterisque |
| `Citation[[<Rab> Francois Rabelais.]]` | Citation [Rab] ... [Rab] Francois Rabelais. |

---

## Citations

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `<quote>texte cite</quote>` | `<blockquote>texte cite</blockquote>` |

### Exemple

```
<quote>SPIP c'est plutot bien.</quote>
D'accord, petit canard en plastique :-)
```

---

## Listes

### Listes a puces (non ordonnees)

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `-* Element` | `<li>Element</li>` (dans `<ul>`) |
| `-** Sous-element` | Sous-liste imbriquee |
| `-*** Sous-sous-element` | Sous-sous-liste imbriquee |

> **Note** : Un simple tiret `-` en debut de ligne cree une puce simple (pas une liste structuree). Pour des listes structurees, utiliser `-*`.

### Listes ordonnees (numerotees)

| Raccourci SPIP | Equivalent HTML |
|----------------|----------------|
| `-# Element` | `<li>Element</li>` (dans `<ol>`) |
| `-## Sous-element` | Sous-liste ordonnee imbriquee |

### Exemples

**Liste a puces imbriquee :**
```
-* Votre cheval est :
-** alezan ;
-** bai ;
-** noir ;
-* mais mon lapin est
-** blanc :
-*** angora
-*** ou a poil ras.
```

**Liste ordonnee :**
```
-# premier
-# deuxieme
-# troisieme
```

---

## Tableaux

### Syntaxe de base

Les cellules sont separees par le symbole `|` (barre verticale). La ligne doit commencer et finir par `|`. Des lignes vides doivent entourer le tableau.

```
| {{Nom}} | {{Prenom}} | {{Age}} |
| Dupont | Jean | 23 ans |
| Capitaine | | inconnu |
| Martin | Philippe | 46 ans |
```

> La premiere ligne en gras (`{{...}}`) est interpretee comme l'en-tete du tableau.

### Legende et resume (accessibilite)

```
|||Legende du tableau|Resume pour l'accessibilite||
| {{Colonne 1}} | {{Colonne 2}} |
| Donnee 1 | Donnee 2 |
```

Pour specifier uniquement le resume : `|| |resume||`

### Fusion de cellules

| Raccourci SPIP | Description |
|----------------|-------------|
| `|<|` | Fusionne avec la cellule precedente (horizontalement) |
| `|^|` | Fusionne avec la cellule au-dessus (verticalement) |

### Exemple de fusion

```
| {{Col 1}} | {{Col 2}} | {{Col 3}} |
| Ligne 1 | L1C2 et L1C3 |<|
| Ligne 2 | L2C2 et L3C2 | L2C3 |
| Ligne 3 |^| L3C3 |
```

---

## Glossaire externe

| Raccourci SPIP | Description |
|----------------|-------------|
| `[?terme]` | Lien vers la definition dans le glossaire externe (Wikipedia par defaut) |
| `[?terme#man2]` | Lien vers le glossaire nomme "man", section 2 |

### Exemple

```
{Frankenstein} est le chef-d'oeuvre de [?Mary Shelley].
```

---

## Ancres nommees

| Raccourci SPIP | Equivalent HTML | Description |
|----------------|----------------|-------------|
| `[nom<-]` | `<a name="nom"></a>` | Definition d'une ancre |
| `[texte->#nom]` | `<a href="#nom">texte</a>` | Lien vers une ancre dans la meme page |
| `[texte->art123#nom]` | `<a href="/article123#nom">texte</a>` | Lien vers une ancre dans un autre article |

---

## Code source

| Raccourci SPIP | Description | Equivalent HTML |
|----------------|-------------|----------------|
| `<code>code</code>` | Code en ligne ou bloc | `<pre><code>code</code></pre>` |
| `<cadre>code</cadre>` | Code dans une zone de texte (textarea) | `<textarea>code</textarea>` |

Le raccourci `<cadre>` est utile pour faciliter le copier-coller du code.

---

## Desactivation des raccourcis

| Raccourci SPIP | Description |
|----------------|-------------|
| `<HTML>texte brut</HTML>` | Le contenu n'est pas traite par les raccourcis SPIP |

Utilise pour inserer du HTML brut ou du code qui ne doit pas etre interprete.

---

## Typographie automatique francaise

Lorsque la langue principale du site SPIP est le francais, SPIP applique automatiquement les regles typographiques francaises :

| Regle | Description |
|-------|-------------|
| Espace insecable avant `:` `;` `!` `?` | Ajout automatique |
| Guillemets francais `<<` `>>` | Espaces insecables avant et apres |

> **Note** : Cette fonctionnalite n'est active que si la langue principale du site est le francais.

---

## Table de conversion complete

Table de reference pour la migration, listant tous les raccourcis SPIP et leur equivalent HTML standard :

| Raccourci SPIP | HTML equivalent | Categorie |
|----------------|----------------|-----------|
| `{texte}` | `<em>texte</em>` | Mise en forme |
| `{{texte}}` | `<strong>texte</strong>` | Mise en forme |
| `{{{texte}}}` | `<h3>texte</h3>` | Intertitre |
| `----` | `<hr />` | Separation |
| `[texte->url]` | `<a href="url">texte</a>` | Lien externe |
| `[->url]` | `<a href="url">url</a>` | Lien externe (auto) |
| `[texte->artN]` | `<a href="/node/X">texte</a>` | Lien interne article |
| `[texte->rubN]` | `<a href="/node/X">texte</a>` | Lien interne rubrique |
| `[texte->brN]` | `<a href="/node/X">texte</a>` | Lien interne breve |
| `[texte->autN]` | `<a href="/user/X">texte</a>` | Lien interne auteur |
| `[texte->motN]` | `<a href="/taxonomy/term/X">texte</a>` | Lien interne mot-cle |
| `[texte->docN]` | `<a href="/media/X">texte</a>` | Lien interne document |
| `[texte->imgN]` | `<a href="/media/X">texte</a>` | Lien interne image |
| `[[note]]` | `<span class="note">...</span>` + note | Note de bas de page |
| `[[<N> note]]` | Note avec numero impose | Note manuelle |
| `[[<*> note]]` | Note avec asterisque | Note manuelle |
| `<quote>texte</quote>` | `<blockquote>texte</blockquote>` | Citation |
| `-* element` | `<ul><li>element</li></ul>` | Liste a puces |
| `-# element` | `<ol><li>element</li></ol>` | Liste ordonnee |
| `\| cel1 \| cel2 \|` | `<table><tr><td>...</td></tr></table>` | Tableau |
| `[?terme]` | `<a href="https://fr.wikipedia.org/wiki/terme">terme</a>` | Glossaire |
| `[nom<-]` | `<a name="nom"></a>` | Ancre |
| `[texte->#nom]` | `<a href="#nom">texte</a>` | Lien vers ancre |
| `<code>...</code>` | `<pre><code>...</code></pre>` | Code source |
| `<cadre>...</cadre>` | `<textarea>...</textarea>` | Code (textarea) |
| `<HTML>...</HTML>` | Contenu brut (non traite) | Echappement |

> **Note pour la migration vers Drupal** : Les liens internes SPIP (`[->artN]`, `[->rubN]`, etc.) doivent etre convertis en utilisant la table de correspondance entre les IDs SPIP (`field_spip_id`) et les chemins Drupal. Les autres raccourcis peuvent etre convertis par un filtre de texte ou un script de pre-traitement lors de la migration.
