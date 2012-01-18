#ifndef _HASH_TABLE_H_
#define _HASH_TABLE_H_ 1

#define HASH_TABLE_INIT_SIZE 6
#define HASH_INDEX(ht, key) (hash_str((key)) % (ht)->size)

#if defined(DEBUG)
#  define LOG_MSG printf
#else
#  define LOG_MSG(...)
#endif

#define SUCCESS 0
#define FAILED -1

typedef struct _Bucket
{
	char *key;
	void *value;
	struct _Bucket *next;
} Bucket;

typedef struct _HashTable
{
	int size;		// 哈希表的大小
	int elem_num;	// 已经保存元素的个数
	Bucket **buckets;
} HashTable;

int hash_init(HashTable *ht);
int hash_lookup(HashTable *ht, char *key, void **result);
int hash_insert(HashTable *ht, char *key, void *value);
int hash_remove(HashTable *ht, char *key);
int hash_destroy(HashTable *ht);
#endif
