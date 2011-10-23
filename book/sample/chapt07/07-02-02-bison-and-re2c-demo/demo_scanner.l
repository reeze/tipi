#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "demo_scanner_def.h"
#include "demo_scanner.h"
#include "demo.tab.h"

/**
 * PHP源码中的字符串copy函数
 */
void *estrndup(void *ptr, int len){
	char *dst = malloc(len + 1);
	memcpy(dst, ptr, len);
	dst[len] = 0;
	return dst;
}

# define copy_value(zendlval, yytext,yyleng) \
	do{\
		zendlval->value.str.val = (char *) estrndup(yytext, yyleng); \
		zendlval->value.str.len = yyleng;\
	}while(0)

Scanner scanner_globals;

#define YYCTYPE char
#define YYFILL(n) 
#define STATE(name)  yyc##name
#define BEGIN(state) YYSETCONDITION(STATE(state))
#define LANG_SCNG(v) (scanner_globals.v)
#define SCNG    LANG_SCNG

#define SAVE_TOKEN() copy_value(zendlval, yytext, yyleng)	//	保存当前获取的字符串
#define YYGETCONDITION()  SCNG(yy_state)
#define YYSETCONDITION(s) SCNG(yy_state) = s
#define YYCURSOR  SCNG(yy_cursor)
#define YYLIMIT   SCNG(yy_limit)
#define YYMARKER  SCNG(yy_marker)
#define yytext       ((char*)SCNG(yy_text))
#define yyleng       SCNG(yy_leng)

/**
 * 搜索一个token
 */ 
int scan(zval *zendlval){
	SCNG(yy_text) = YYCURSOR;
	/*!re2c
	  <!*> {yyleng = YYCURSOR - SCNG(yy_text);}


	  <INITIAL>"<?php" {BEGIN(ST_IN_SCRIPTING); return T_BEGIN;}
	  <ST_IN_SCRIPTING>[0-9]+ {
	  SAVE_TOKEN();
	  return T_NUMBER;
	  }
	  <ST_IN_SCRIPTING>[ \n\t\r]+ {return T_WHITESPACE;}
	  <ST_IN_SCRIPTING>"exit" { return T_EXIT; }
	  <ST_IN_SCRIPTING>[a-z]+ {
	  SAVE_TOKEN();
	  return T_LOWER_CHAR;
	  }
	  <ST_IN_SCRIPTING>[A-Z]+ {
	  SAVE_TOKEN();
	  return T_UPPER_CHAR;
	  }
	  <ST_IN_SCRIPTING>"?>" {BEGIN(INITIAL); return T_END;}
	  <ST_IN_SCRIPTING>[\000] {BEGIN(INITIAL); return T_END;}


	  <ST_IN_SCRIPTING>[^] {return T_UNKNOWN;}
	  <*>[^] {return T_INPUT_ERROR;}
	 */
}

/** 
 * bison执行中必须存在的yylex函数
 */
int yylex(znode *zendlval) {
	int token;

	while(token = scan(&zendlval->constant)) {
		zendlval->op_type = token;

		if (token == T_INPUT_ERROR) {
			printf("%s\n", "input error");
			break;
		}
		if (token == T_END) {
			printf("%s\n", "end");
			break;
		}
		return token;
	}
	return 0;


}
int main(int argc, char* argv[])
{
	BEGIN(INITIAL);	//	全局初始化，需要放在scan调用之前
	scanner_globals.yy_cursor = argv[1];	//将输入的第一个参数作为要解析的字符串

	yyparse();
	return 0;
}
