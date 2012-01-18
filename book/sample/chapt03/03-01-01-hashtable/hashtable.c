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
	ht->buckets		= (Bucket **)calloc(ht->size, sizeof(Bucket *));

	if(ht->buckets == NULL) return FAILED;

	LOG_MSG("[init]\tsize: %i\n", ht->size);

	return SUCCESS;
}

int hash_lookup(HashTable *ht, char *key, void **result)
{
	int index = HASH_INDEX(ht, key);
	Bucket *bucket = ht->buckets[index];

	if(bucket == NULL) goto failed;

	while(bucket)
	{
		if(strcmp(bucket->key, key) == 0)
		{
			LOG_MSG("[lookup]\t found %s\tindex:%i value: %p\n",
				key, index, bucket->value);
			*result = bucket->value;	

			return SUCCESS;
		}

		bucket = bucket->next;
	}

failed:
	LOG_MSG("[lookup]\t key:%s\tfailed\t\n", key);
	return FAILED;
}

int hash_insert(HashTable *ht, char *key, void *value)
{
	// check if we need to resize the hashtable
	resize_hash_table_if_needed(ht);

	int index = HASH_INDEX(ht, key);

	Bucket *org_bucket = ht->buckets[index];
	Bucket *tmp_bucket = org_bucket;

	// check if the key exits already
	while(tmp_bucket)
	{
		if(strcmp(key, tmp_bucket->key) == 0)
		{
			LOG_MSG("[update]\tkey: %s\n", key);
			tmp_bucket->value = value;

			return SUCCESS;
		}

		tmp_bucket = tmp_bucket->next;
	}

	Bucket *bucket = (Bucket *)malloc(sizeof(Bucket));

	bucket->key	  = key;
	bucket->value = value;
	bucket->next  = NULL;

	ht->elem_num += 1;

	if(org_bucket != NULL)
	{
		LOG_MSG("[collision]\tindex:%d key:%s\n", index, key);
		bucket->next = org_bucket;
	}

	ht->buckets[index]= bucket;

	LOG_MSG("[insert]\tindex:%d key:%s\tht(num:%d)\n",
		index, key, ht->elem_num);

	return SUCCESS;
}

int hash_remove(HashTable *ht, char *key)
{
	int index = HASH_INDEX(ht, key);
	Bucket *bucket  = ht->buckets[index];
	Bucket *prev	= NULL;

	if(bucket == NULL) return FAILED;

	// find the right bucket from the link list 
	while(bucket)
	{
		if(strcmp(bucket->key, key) == 0)
		{
			LOG_MSG("[remove]\tkey:(%s) index: %d\n", key, index);

			if(prev == NULL)
			{
				ht->buckets[index] = bucket->next;
			}
			else
			{
				prev->next = bucket->next;
			}
			free(bucket);

			return SUCCESS;
		}

		prev   = bucket;
		bucket = bucket->next;
	}

	LOG_MSG("[remove]\t key:%s not found remove \tfailed\t\n", key);
	return FAILED;
}

int hash_destroy(HashTable *ht)
{
	int i;
	Bucket *cur = NULL;
	Bucket *tmp = NULL;

	for(i=0; i < ht->size; ++i)
	{
		cur = ht->buckets[i];
		while(cur)
		{
			tmp = cur;
			cur = cur->next;
			free(tmp);
		}
	}
	free(ht->buckets);

	return SUCCESS;
}

static int hash_str(char *key)
{
	int hash = 0;

	char *cur = key;

	while(*(cur++) != '\0')
	{
		hash +=	*cur;
	}

	return hash;
}

static int hash_resize(HashTable *ht)
{
	// double the size
	int org_size = ht->size;
	ht->size = ht->size * 2;
	ht->elem_num = 0;

	LOG_MSG("[resize]\torg size: %i\tnew size: %i\n", org_size, ht->size);

	Bucket **buckets = (Bucket **)calloc(ht->size, sizeof(Bucket **));

	Bucket **org_buckets = ht->buckets;
	ht->buckets = buckets;

	int i = 0;
	for(i=0; i < org_size; ++i)
	{
		Bucket *cur = org_buckets[i];
		Bucket *tmp;
		while(cur) 
		{
			// rehash: insert again
			hash_insert(ht, cur->key, cur->value);

			// free the org bucket, but not the element
			tmp = cur;
			cur = cur->next;
			free(tmp);
		}
	}
	free(org_buckets);

	LOG_MSG("[resize] done\n");

	return SUCCESS;
}

// if the elem_num is almost as large as the capacity of the hashtable
// we need to resize the hashtable to contain enough elements
static void resize_hash_table_if_needed(HashTable *ht)
{
	if(ht->size - ht->elem_num < 1)
	{
		hash_resize(ht);	
	}
}
