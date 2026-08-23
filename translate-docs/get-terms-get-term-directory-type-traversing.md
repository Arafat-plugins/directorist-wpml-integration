# get_terms/get_term er moddhe Directory Type Kivabe Ber Korbo

Goal:

```text
Codebase-e get_terms(), get_term(), get_term_by() er moddhe directory type taxonomy use hocche kina terminal command diye ber kora.
```

Directory type taxonomy identify korar keyword:

```text
ATBDP_DIRECTORY_TYPE
atbdp_listing_types
get_directory_taxonomy()
directory_type
```

## 1. First command: sob term function call ber koro

Command:

```bash
cd "/c/laragon/www/Directorist-wpml/wp-content/plugins/directorist-wpml-integration"

grep -RIn -C 5 --include='*.php' -E "get_terms[[:space:]]*\\(|get_term[[:space:]]*\\(|get_term_by[[:space:]]*\\(" app
```

Meaning:

```text
grep      = text search
-R        = subfolder shoho recursive search
-I        = binary file ignore
-n        = line number show
-C 5      = match-er age-pore 5 line context show
--include = only .php file search
-E        = extended regex use
```

Expected:

```text
get_terms(), get_term(), get_term_by() call gula context shoho show korbe.
Context dekhe bujhte parba taxonomy directory type kina.
```

## 2. Directory type keyword shoho narrow koro

Command:

```bash
grep -RIn -C 6 --include='*.php' -E "get_terms[[:space:]]*\\(|get_term[[:space:]]*\\(|get_term_by[[:space:]]*\\(|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|get_directory_taxonomy|directory_type" app \
  > translate-docs/get-term-directory-type-search.txt
```

Expected:

```text
Terminal-e output show korbe na.
Result save hobe:
translate-docs/get-term-directory-type-search.txt
```

File dekhte:

```bash
sed -n '1,180p' translate-docs/get-term-directory-type-search.txt
```

## 3. Only direct directory type matches dekhte chaile

Command:

```bash
grep -RIn --include='*.php' -E "ATBDP_DIRECTORY_TYPE|atbdp_listing_types|get_directory_taxonomy" app
```

Expected:

```text
Je file/line gula directory type taxonomy directly use kore, segula show korbe.
Tarpor oi nearby file-e get_terms/get_term ache kina check korba.
```

## 4. get_terms-er moddhe directory type ache kina

Command:

```bash
grep -RIn -B 6 -A 10 --include='*.php' "get_terms" app \
  | grep -E "get_terms|taxonomy|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|directory_type"
```

Expected current result:

```text
app/Controller/Ajax/Get_Directory_Type_Translations.php
```

Why:

```php
$taxonomy = ATBDP_DIRECTORY_TYPE;

$directory_types = get_terms([
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
]);
```

Meaning:

```text
Eikhane get_terms() diye directory type taxonomy-er sob term load hocche.
```

## 5. get_term-er moddhe directory type ache kina

Command:

```bash
grep -RIn -B 6 -A 10 --include='*.php' "get_term(" app \
  | grep -E "get_term|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|get_directory_taxonomy|directory_taxonomy|term->taxonomy"
```

Expected current important files:

```text
app/Controller/Hook/Directory_Builder_String_Package.php
app/Controller/Hook/Directory_Type_Meta_Translation.php
app/Controller/Hook/Directory_Translation.php
```

Meaning:

```text
Directory_Builder_String_Package.php
- get_term( $source_directory_id, $this->get_directory_taxonomy() )
- clearly directory type.

Directory_Type_Meta_Translation.php
- get_term( $term_id )
- tarpor $term->taxonomy check kore ATBDP_DIRECTORY_TYPE / atbdp_listing_types kina.

Directory_Translation.php
- translated default directory term get_term() diye load kore.
```

## 6. get_term_by-er moddhe directory type ache kina

Command:

```bash
grep -RIn -B 5 -A 8 --include='*.php' "get_term_by" app \
  | grep -E "get_term_by|get_directory_taxonomy|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|directory_type"
```

Expected current important file:

```text
app/Controller/Hook/Directory_Builder_String_Package.php
```

Meaning:

```text
Request/query theke directory_type slug pele get_term_by() diye directory type term resolve kore.
```

## 7. Output JSON file-e save korte chaile

`jq` thakle:

```bash
grep -RIn --include='*.php' -E "get_terms[[:space:]]*\\(|get_term[[:space:]]*\\(|get_term_by[[:space:]]*\\(|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|get_directory_taxonomy" app \
  | jq -Rn '[inputs | capture("(?<file>.*?):(?<line>[0-9]+):(?<text>.*)") | .line |= tonumber]' \
  > translate-docs/get-term-directory-type-search.json
```

JSON dekhte:

```bash
sed -n '1,120p' translate-docs/get-term-directory-type-search.json
```

`jq` na thakle `.txt` file-e save kora best:

```bash
grep -RIn --include='*.php' -E "get_terms|get_term|get_term_by|ATBDP_DIRECTORY_TYPE|atbdp_listing_types|get_directory_taxonomy" app \
  > translate-docs/get-term-directory-type-search.txt
```

## Short rule

```text
get_terms() normally multiple terms load kore.
get_term() normally single term load kore.
get_term_by() slug/name/id diye single term resolve kore.

Directory type kina bujhte nearby line-e ei keyword khujba:
ATBDP_DIRECTORY_TYPE
atbdp_listing_types
get_directory_taxonomy()
```
