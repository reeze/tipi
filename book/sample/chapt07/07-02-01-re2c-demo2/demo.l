#include <stdio.h>
#include "demo_def.h"
#include "demo.h"

Scanner scanner_globals;

#define YYCTYPE char
#define YYFILL(n) 
#define STATE(name)  yyc##name
#define BEGIN(state) YYSETCONDITION(STATE(state))
#define LANG_SCNG(v) (scanner_globals.v)
#define SCNG    LANG_SCNG

#define YYGETCONDITION()  SCNG(yy_state)
#define YYSETCONDITION(s) SCNG(yy_state) = s
#define YYCURSOR  SCNG(yy_cursor)
#define YYLIMIT   SCNG(yy_limit)
#define YYMARKER  SCNG(yy_marker)

int scan(){
	/*!re2c

	  <INITIAL>"<?php" {BEGIN(ST_IN_SCRIPTING); return T_BEGIN;}
	  <ST_IN_SCRIPTING>[0-9]+ {return T_NUMBER;}
	  <ST_IN_SCRIPTING>[ \n\t\r]+ {return T_WHITESPACE;}
	  <ST_IN_SCRIPTING>"exit" { return T_EXIT; }
	  <ST_IN_SCRIPTING>[a-z]+ {return T_LOWER_CHAR;}
	  <ST_IN_SCRIPTING>[A-Z]+ {return T_UPPER_CHAR;}
	  <ST_IN_SCRIPTING>"?>" {BEGIN(INITIAL); return T_END;}
          <ST_IN_SCRIPTING>[\000] {BEGIN(INITIAL); return T_END;}


	  <ST_IN_SCRIPTING>[^] {return T_UNKWON;}
	  <*>[^] {return T_INPUT_ERROR;}
	 */
}

void print_token(int token) {
	switch (token) {
		case T_BEGIN: printf("%s\n", "begin");break;
		case T_NUMBER: printf("%s\n", "number");break;
		case T_LOWER_CHAR: printf("%s\n", "lower char");break;
		case T_UPPER_CHAR: printf("%s\n", "upper char");break;
		case T_EXIT: printf("%s\n", "exit");break;
		case T_UNKWON: printf("%s\n", "unkown");break;
		case T_INPUT_ERROR: printf("%s\n", "input error");break;
		case T_END: printf("%s\n", "end");break;
	}
}
int main(int argc, char* argv[])
{
	int token;
	BEGIN(INITIAL);	//	全局初始化，需要放在scan调用之前
	scanner_globals.yy_cursor = argv[1];	//将输入的第一个参数作为要解析的字符串

	while(token = scan()) {
		if (token == T_INPUT_ERROR) {
			printf("%s\n", "input error");
			break;
		}
		if (token == T_END) {
			printf("%s\n", "end");
			break;
		}
		print_token(token);
	}

	return 0;
}
