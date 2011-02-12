# 类型提示的实现

non_empty_parameter_list:
		optional_class_type T_VARIABLE				{ znode tmp;  fetch_simple_variable(&tmp, &$2, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$1, &$2, 0 TSRMLS_CC); }
	|	optional_class_type '&' T_VARIABLE			{ znode tmp;  fetch_simple_variable(&tmp, &$3, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$1, &$3, 1 TSRMLS_CC); }
	|	optional_class_type '&' T_VARIABLE '=' static_scalar			{ znode tmp;  fetch_simple_variable(&tmp, &$3, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$5, &$1, &$3, 1 TSRMLS_CC); }
	|	optional_class_type T_VARIABLE '=' static_scalar				{ znode tmp;  fetch_simple_variable(&tmp, &$2, 0 TSRMLS_CC); $$.op_type = IS_CONST; Z_LVAL($$.u.constant)=1; Z_TYPE($$.u.constant)=IS_LONG; INIT_PZVAL(&$$.u.constant); zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$4, &$1, &$2, 0 TSRMLS_CC); }
	|	non_empty_parameter_list ',' optional_class_type T_VARIABLE 	{ znode tmp;  fetch_simple_variable(&tmp, &$4, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$3, &$4, 0 TSRMLS_CC); }
	|	non_empty_parameter_list ',' optional_class_type '&' T_VARIABLE	{ znode tmp;  fetch_simple_variable(&tmp, &$5, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV, &tmp, &$$, NULL, &$3, &$5, 1 TSRMLS_CC); }
	|	non_empty_parameter_list ',' optional_class_type '&' T_VARIABLE	 '=' static_scalar { znode tmp;  fetch_simple_variable(&tmp, &$5, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$7, &$3, &$5, 1 TSRMLS_CC); }
	|	non_empty_parameter_list ',' optional_class_type T_VARIABLE '=' static_scalar 	{ znode tmp;  fetch_simple_variable(&tmp, &$4, 0 TSRMLS_CC); $$=$1; Z_LVAL($$.u.constant)++; zend_do_receive_arg(ZEND_RECV_INIT, &tmp, &$$, &$6, &$3, &$4, 0 TSRMLS_CC); }
;

optional_class_type:
		/* empty */					{ $$.op_type = IS_UNUSED; }
	|	fully_qualified_class_name	{ $$ = $1; }
	|	T_ARRAY						{ $$.op_type = IS_CONST; Z_TYPE($$.u.constant)=IS_NULL;}
;

fully_qualified_class_name:
		namespace_name { $$ = $1; }
	|	T_NAMESPACE T_NS_SEPARATOR namespace_name { $$.op_type = IS_CONST; ZVAL_EMPTY_STRING(&$$.u.constant);  zend_do_build_namespace_name(&$$, &$$, &$3 TSRMLS_CC); }
	|	T_NS_SEPARATOR namespace_name { char *tmp = estrndup(Z_STRVAL($2.u.constant), Z_STRLEN($2.u.constant)+1); memcpy(&(tmp[1]), Z_STRVAL($2.u.constant), Z_STRLEN($2.u.constant)+1); tmp[0] = '\\'; efree(Z_STRVAL($2.u.constant)); Z_STRVAL($2.u.constant) = tmp; ++Z_STRLEN($2.u.constant); $$ = $2; }
;

namespace_name:
		T_STRING { $$ = $1; }
	|	namespace_name T_NS_SEPARATOR T_STRING { zend_do_build_namespace_name(&$$, &$1, &$3 TSRMLS_CC); }


static inline int zend_verify_arg_type(zend_function *zf, zend_uint arg_num, zval *arg, ulong fetch_type TSRMLS_DC)
{
	zend_arg_info *cur_arg_info;
	char *need_msg;
	zend_class_entry *ce;

	if (!zf->common.arg_info
		|| arg_num>zf->common.num_args) {
		return 1;
	}

	cur_arg_info = &zf->common.arg_info[arg_num-1];

	if (cur_arg_info->class_name) {
		const char *class_name;

		if (!arg) {
			need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
			return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, "none", "" TSRMLS_CC);
		}
		if (Z_TYPE_P(arg) == IS_OBJECT) {
			need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
			if (!ce || !instanceof_function(Z_OBJCE_P(arg), ce TSRMLS_CC)) {
				return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, "instance of ", Z_OBJCE_P(arg)->name TSRMLS_CC);
			}
		} else if (Z_TYPE_P(arg) != IS_NULL || !cur_arg_info->allow_null) {
			need_msg = zend_verify_arg_class_kind(cur_arg_info, fetch_type, &class_name, &ce TSRMLS_CC);
			return zend_verify_arg_error(zf, arg_num, cur_arg_info, need_msg, class_name, zend_zval_type_name(arg), "" TSRMLS_CC);
		}
	} else if (cur_arg_info->array_type_hint) {
		if (!arg) {
			return zend_verify_arg_error(zf, arg_num, cur_arg_info, "be an array", "", "none", "" TSRMLS_CC);
		}
		if (Z_TYPE_P(arg) != IS_ARRAY && (Z_TYPE_P(arg) != IS_NULL || !cur_arg_info->allow_null)) {
			return zend_verify_arg_error(zf, arg_num, cur_arg_info, "be an array", "", zend_zval_type_name(arg), "" TSRMLS_CC);
		}
	}
	return 1;
}


static inline char * zend_verify_arg_class_kind(const zend_arg_info *cur_arg_info, ulong fetch_type, const char **class_name, zend_class_entry **pce TSRMLS_DC)
{
	*pce = zend_fetch_class(cur_arg_info->class_name, cur_arg_info->class_name_len, (fetch_type | ZEND_FETCH_CLASS_AUTO | ZEND_FETCH_CLASS_NO_AUTOLOAD) TSRMLS_CC);

	*class_name = (*pce) ? (*pce)->name: cur_arg_info->class_name;
	if (*pce && (*pce)->ce_flags & ZEND_ACC_INTERFACE) {
		return "implement interface ";
	} else {
		return "be an instance of ";
	}
}

static inline int zend_verify_arg_error(const zend_function *zf, zend_uint arg_num, const zend_arg_info *cur_arg_info, const char *need_msg, const char *need_kind, const char *given_msg, char *given_kind TSRMLS_DC)
{
	zend_execute_data *ptr = EG(current_execute_data)->prev_execute_data;
	char *fname = zf->common.function_name;
	char *fsep;
	char *fclass;

	if (zf->common.scope) {
		fsep =  "::";
		fclass = zf->common.scope->name;
	} else {
		fsep =  "";
		fclass = "";
	}

	if (ptr && ptr->op_array) {
		zend_error(E_RECOVERABLE_ERROR, "Argument %d passed to %s%s%s() must %s%s, %s%s given, called in %s on line %d and defined", arg_num, fclass, fsep, fname, need_msg, need_kind, given_msg, given_kind, ptr->op_array->filename, ptr->opline->lineno);
	} else {
		zend_error(E_RECOVERABLE_ERROR, "Argument %d passed to %s%s%s() must %s%s, %s%s given", arg_num, fclass, fsep, fname, need_msg, need_kind, given_msg, given_kind);
	}
	return 0;
}