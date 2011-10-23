
typedef struct Scanner {
    int			fd;
    unsigned char		*yy_cursor, *yy_limit, *yy_marker, *yy_text;
    int 	yy_state, yy_leng;
} Scanner;


typedef union _zvalue_value {
    long lval;                  /* long value */
    double dval;                /* double value */
    struct {
            char *val;
            int len;
    } str;
} zvalue_value;

typedef struct _zval_struct {
	/* Variable information */
	zvalue_value value;     /* value */
	int type;    /* active type */
}zval;

typedef struct _znode {
	int op_type;
	zval constant;
}znode;

int yylex(znode * zendlval);
