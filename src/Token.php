<?php

namespace Pbit\Evaluator;


class Token {
    const EOF = 'eof';
    const OPEN_PARENTHESIS = 'open_parenthesis';
    const CLOSE_PARENTHESIS = 'close_parenthesis';
    
    const VARIABLE_NAME = 'variable_name';
    
    const OPERATOR_AND = 'and';
    const OPERATOR_OR = 'or';
    
    const OPERATOR_EQUAL = 'equal';
    const OPERATOR_NOT_EQUAL = 'not_equal';
    
    const OPERATOR_GT = 'gt';
    const OPERATOR_LT = 'lt';
    
    const OPERATOR_GET = 'get';
    const OPERATOR_LET = 'let';
    
    
    const SINGLE_QUOTE = 'single_quote';
    
    const DOUBLE_QUOTE = 'double_quote';
    
    const OPERATOR_RLIKE = 'operator_rlike';

    const OPERATOR_CONTAIN = 'operator_contain';
    const OPERATOR_NOT_CONTAIN = 'operator_not_contain';
    
    
    
    public $type;
    public $value;
    
    function __construct($type, $value = null) {
        $this->type = $type;
        $this->value = $value;
    }
    

}
