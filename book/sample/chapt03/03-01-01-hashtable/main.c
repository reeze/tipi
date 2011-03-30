#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <string.h>
#include "HashTable.h"

int main(int argc, char **argv)
{
	HashTable *ht = (HashTable *)malloc(sizeof(HashTable));
	int result = hash_init(ht);

	assert(result == SUCCESS);

	int i = 10;
	char str[] = "Hello TIPI";

	hash_insert(ht, "KeyInt", &i);
	hash_insert(ht, "KeyStr", str);

	int *j;
	char *find_str;

	hash_lookup(ht, "KeyInt", (void **)&j);
	hash_lookup(ht, "KeyStr", (void **)&find_str);

	assert(strcmp(find_str, str) == 0);
	assert(*j = i);

	hash_remove(ht, "testI");
	result = hash_lookup(ht, "testI", (void **)&j);

	assert(result == FAILED);

	hash_destroy(ht);
	ht = NULL;

	printf("Woohoo, It looks like HashTable works properly\n");

	return 0;
}
