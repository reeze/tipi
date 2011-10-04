
#define T_BEGIN 1
#define T_NUMBER 2
#define T_LOWER_CHAR 3
#define T_UPPER_CHAR 4
#define T_EXIT 5 
#define T_UNKWON 6
#define T_INPUT_ERROR 7
#define T_END 8 
#define T_WHITESPACE 9

typedef struct Scanner {
    int			fd;
    unsigned char		*yy_cursor, *yy_limit, *yy_marker;
    int 	yy_state;
} Scanner;
