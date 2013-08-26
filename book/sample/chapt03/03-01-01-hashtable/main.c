#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <string.h>
#include "hashtable.h"

#define TEST(tcase) printf(">>> [START CASE] " tcase "<<<\n")
#define PASS(tcase) printf(">>> [PASSED] " tcase " <<<\n")

int main(int argc, char **argv)
{
	HashTable *ht = (HashTable *)malloc(sizeof(HashTable));
	int result = hash_init(ht);

	assert(result == SUCCESS);

	/* Data */
	int  int1 = 10;
	int  int2 = 20;
	char str1[] = "Hello TIPI";
	char str2[] = "Value";
	/* to find data container */
	int *j = NULL;
	char *find_str = NULL;

	/* Test Key insert */
	TEST("Key insert");
	hash_insert(ht, "KeyInt", &int1);
	hash_insert(ht, "asdfKeyStrass", str1);
	hash_insert(ht, "K13eyStras", str1);
	hash_insert(ht, "KeyStr5", str1);
	hash_insert(ht, "KeyStr", str1);
	PASS("Key insert");

	/* Test key lookup */
	TEST("Key lookup");
	hash_lookup(ht, "KeyInt", (void **)&j);
	hash_lookup(ht, "KeyStr", (void **)&find_str);

	assert(strcmp(find_str, str1) == 0);
	assert(*j = int1);
	PASS("Key lookup");

	/* Test Key update */
	TEST("Test key update");
	hash_insert(ht, "KeyInt", &int2);
	hash_lookup(ht, "KeyInt", (void **)&j);
	assert(*j = int2);
	PASS("Test key update");

	TEST(">>>	 Test key not found		<<< ");
	result = hash_lookup(ht, "non-exits-key", (void **)&j);
	assert(result == FAILED);
	PASS("non-exist-key lookup");

	TEST("Test key not found after remove");
	char strMyKey[] = "My-Key-Value";
	find_str = NULL;
	hash_insert(ht, "My-Key", &strMyKey);
	result = hash_remove(ht, "My-Key");
	assert(result == SUCCESS);

	result = hash_lookup(ht, "My-Key", (void **)&find_str);
	assert(find_str == NULL);
	assert(result == FAILED);
	PASS("Test key not found after remove");

	PASS(">>>	 Test key not found		<<< ");

	TEST("Add many elements and make hashtable rehash");
	hash_insert(ht, "a1", &int2);
	hash_insert(ht, "a2", &int1);
	hash_insert(ht, "a3", &int1);
	hash_insert(ht, "a4", &int1);
	hash_insert(ht, "a5", &int1);
	hash_insert(ht, "a6", &int1);
	hash_insert(ht, "a7", &int1);
	hash_insert(ht, "a8", str2);
	hash_insert(ht, "a9", &int1);
	hash_insert(ht, "a10", &int1);
	hash_insert(ht, "a11", &int1);
	hash_insert(ht, "a12", &int1);
	hash_insert(ht, "a13", &int1);
	hash_insert(ht, "a14", &int1);
	hash_insert(ht, "a15", &int1);
	hash_insert(ht, "a16", &int1);
	hash_insert(ht, "a17", &int1);
	hash_insert(ht, "a18", &int1);
	hash_insert(ht, "a19", &int1);
	hash_insert(ht, "a20", &int1);
	hash_insert(ht, "a21", &int1);
	hash_insert(ht, "a22", &int1);
	hash_insert(ht, "a23", &int1);
	hash_insert(ht, "a24", &int1);
	hash_insert(ht, "a24", &int1);
	hash_insert(ht, "a24", &int1);
	hash_insert(ht, "a25", &int1);
	hash_insert(ht, "a26", &int1);
	hash_insert(ht, "a27", &int1);
	hash_insert(ht, "a28", &int1);
	hash_insert(ht, "a29", &int1);
	hash_insert(ht, "a30", &int1);
	hash_insert(ht, "a31", &int1);
	hash_insert(ht, "a32", &int1);
	hash_insert(ht, "a33", &int1);

	hash_lookup(ht, "a23", (void **)&j);
	assert(*j = int1);
	hash_lookup(ht, "a30", (void **)&j);
	assert(*j = int1);
	PASS("Add many elements and make hashtable rehash");

	hash_destroy(ht);
	free(ht);

	printf("Woohoo, It looks like HashTable works properly\n");

	return 0;
}
