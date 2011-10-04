#include <stdio.h>

char *scan(char *p){
#define YYCTYPE char
#define YYCURSOR p
#define YYLIMIT p
#define YYMARKER q
#define YYFILL(n)
	/*!re2c
	  [0-9]+ {return "number";}
	  [a-z]+ {return "lower";}
	  [A-Z]+ {return "upper";}
	  [^] {return "unkown";}
	 */
}

int main(int argc, char* argv[])
{
	printf("%s\n", scan(argv[1]));

	return 0;
}
