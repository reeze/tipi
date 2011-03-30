#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "hashtable.h"

static void resize_hash_table_if_needed(HashTable *ht);
static int hash_str(char *key);

int hash_init(HashTable *ht)
{
	ht->size 		= HASH_TABLE_INIT_SIZE;
	ht->elem_num 	= 0;
	ht->buckets		= (Bucket **)calloc((size_t)ht->size, sizeof(Bucket *));

	if(ht->buckets == NULL) return FAILED;

	LOG_MSG("HashTable Inited with size: %i\n", ht->size);

	return SUCCESS;
}

int hash_lookup(HashTable *ht, char *key, void **result)
{
	int index = HASH_INDEX(ht, key);
	Bucket *bucket = ht->buckets[index];

	if(bucket == NULL) return FAILED;

	// find the right bucket from the link list 
	while(bucket)
	{
		if(strcmp(bucket->key, key) == 0)
		{
			LOG_MSG("HashTable found key in index: %i with  key: %s value: %p\n", index, key, bucket->value);
			*result = bucket->value;	
			return SUCCESS;
		}

		bucket = bucket->next;
	}

	LOG_MSG("HashTable lookup missed the key: %s\n", key);
	return FAILED;
}

int hash_insert(HashTable *ht, char *key, void *value)
{
	// check if we need to resize the hashtable
	resize_hash_table_if_needed(ht);

	int index = HASH_INDEX(ht, key);

	Bucket *org_bucket = ht->buckets[index];
	Bucket *bucket = (Bucket *)malloc(sizeof(Bucket));

	bucket->key	  = strdup(key);
	bucket->value = value;

	LOG_MSG("Insert data p: %p\n", value);

	ht->elem_num += 1;

	if(org_bucket != NULL) {
		LOG_MSG("Index collision found with org hashtable: %p\n", org_bucket);
		bucket->next = org_bucket;
	}

	ht->buckets[index]= bucket;

	LOG_MSG("Element inserted at index %i, now we have: %i elements\n", index, ht->elem_num);

	return SUCCESS;
}

int hash_remove(HashTable *ht, char *key)
{
	int index = HASH_INDEX(ht, key);
	Bucket *bucket = ht->buckets[index];
	Bucket *prev = NULL;

	if(bucket == NULL) return FAILED;

	// find the right bucket from the link list 
	while(bucket)
	{
		if(strcmp(bucket->key, key) == 0)
		{
			LOG_MSG("Found key in index: %i with  key: %s value: %p\n", index, key, bucket->value);

			if(prev != NULL) {
				prev->next = bucket->next;	
			}

			free(bucket->key);
			free(bucket);
			bucket = NULL;

			return SUCCESS;
		}

		prev   = bucket;
		bucket = bucket->next;
	}

	LOG_MSG("HashTable lookup missed the key: %s, No element deleted\n", key);
	return FAILED;
}

int hash_destroy(HashTable *ht)
{
	// TODO
	return SUCCESS;
}

static int hash_str(char *key)
{
	int hash = 0;

	char *cur = key;

	while(*(cur++) != '\0') {
		hash +=	*cur;
	}

	return hash;
}

static int hash_resize(HashTable *ht)
{
	int org_size = ht->size;
	// double the size
	ht->size = ht->size * 2;

	LOG_MSG("HashTable do resize, org size: %i, with new size: %i\n", org_size, ht->size);

	Bucket **buckets = (Bucket **)calloc(ht->size, sizeof(Bucket *));
	Bucket **org_buckets = ht->buckets;
	ht->buckets = buckets;

	int i = 0;
	for(i=0; i < org_size; ++i)
	{
		Bucket *cur = org_buckets[i];
		while(cur != NULL) 
		{
			// rehash: insert again
			hash_insert(ht, cur->key, cur->value);

			// free the org bucket, but not the element
			free(cur->key);
			free(cur);
			cur = cur->next;
		}
	}

	return SUCCESS;
}

// if the elem_num is almost as large as the capacity of the hashtable
// we need to resize the hashtable to contain enough elements
static void resize_hash_table_if_needed(HashTable *ht)
{
	if(ht->size - ht->elem_num <= 1)
	{
		LOG_MSG("HashTable need resize, size: %i, elem_num: %i\n", ht->size, ht->elem_num);
		hash_resize(ht);	
	}
}
