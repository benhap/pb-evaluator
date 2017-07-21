<?php

namespace Pbit\Evaluator;

use Pbit\Evaluator\Token as Token;

class LogicalEvaluator {

    protected $expression;
    protected $pos;
    protected $expressionLength;
    protected $variableValues;

    function __construct($expression, $variableValues) {
        $this->expression = $expression;
        $this->expressionLength = strlen($expression);
        $this->pos = 0;
        $this->variableValues = $variableValues;
    }

    public function readChar($movePos = true) {

        if ($this->pos >= $this->expressionLength) {
            return null;
        }

        $ch = $this->expression[$this->pos];

        if ($movePos) {
            $this->pos++;
        }

        return $ch;
    }

    public function skipSpaces() {
        while (($ch = $this->readChar()) !== null) {
            if ($ch != "\0" && $ch != "\t" && $ch != "\n" && $ch != "\x0B" && $ch != "\r" && $ch != " ") {
                $this->pos--;
                break;
            }
        }
    }

    public function readAlphaNum() {

        $w = "";

        while (($ch = $this->readChar()) !== null) {
     
            if (preg_match("/^[a-zA-Z0-9\.]+$/", $ch)) {
             
                $w .= $ch;
            } else {
                $this->pos--;
                break;
            }
        }

        return $w;
    }

    /**
     * TODO: FIX escaping....
     * @return type
     */
    public function readVariableValue($separator) {

        $w = "";
        $escapeNext = false;

        // ignore special chars if preceeded by \
        $specialChars = ["\\", $separator];


        while (($ch = $this->readChar()) !== null) {
            if ($ch !== $separator || $escapeNext) {

                if (array_search($ch, $specialChars) !== false) {

                    if ($escapeNext) {
                        $w .= $ch;
                        $escapeNext = false;
                    } else {
                        if ($ch == "\\") {
                            $escapeNext = true;
                        }
                    }
                } else {
                    if ($escapeNext) {
                        $w .= "\\" . $ch;
                    } else {
                        $w .= $ch;
                    }
                    $escapeNext = false;
                }
            } else {
                $this->pos--;
                break;
            }
        }


        

        return $w;
    }

    public function readOperator() {

        $o = "";

        while (($ch = $this->readChar()) !== null) {
            if (array_search($ch, ["<", ">", "!", "=", "~"])) {
                $o .= $ch;
            } else {
                $this->pos--;
                break;
            }
        }

        return $o;
    }

    public function readToken($preview = false) {

        $origPos = $this->pos;

        $this->skipSpaces();
        $ch = $this->readChar(false);

        if ($ch === null) {
            $res = new Token(Token::EOF);
        } elseif ($ch == "(") {
            $this->pos++;
            $res = new Token(Token::OPEN_PARENTHESIS);
        } elseif ($ch == ")") {
            $this->pos++;
            $res = new Token(Token::CLOSE_PARENTHESIS);
        } elseif ($ch == "'") {
            $this->pos++;
            $res = new Token(Token::SINGLE_QUOTE);
        } elseif ($ch == '"') {
            $this->pos++;
            $res = new Token(Token::DOUBLE_QUOTE);
        } elseif ($ch == "=" || $ch == ">" || $ch == "<" || $ch == "!" || $ch == "~") {

            $operator = $this->readOperator();

            if ($operator === "=") {
                $res = new Token(Token::OPERATOR_EQUAL);
            } elseif ($operator === ">") {
                $res = new Token(Token::OPERATOR_GT);
            } elseif ($operator === ">=") {
                $res = new Token(Token::OPERATOR_GET);
            } elseif ($operator === "<") {
                $res = new Token(Token::OPERATOR_LT);
            } elseif ($operator === "<=") {
                $res = new Token(Token::OPERATOR_LET);
            } elseif ($operator === "<>") {
                $res = new Token(Token::OPERATOR_NOT_EQUAL);
            } elseif ($operator === "!=") {
                $res = new Token(Token::OPERATOR_NOT_EQUAL);
            } elseif ($operator === "~") {
                $res = new Token(Token::OPERATOR_RLIKE);
            } else {
                throw new \Exception("Wrong operator: {$operator}");
            }
        } elseif (ctype_alpha($ch)) {

            $w = $this->readAlphaNum();

            if (strtolower($w) == "and") {
                $res = new Token(Token::OPERATOR_AND);
            } elseif (strtolower($w) == "or") {
                $res = new Token(Token::OPERATOR_OR);
            } else {
                $res = new Token(Token::VARIABLE_NAME, $w);
             //   echo $w;
            }
        }

        if ($preview) {
            $this->pos = $origPos;
        }

        return $res;
    }

    public function expect($type) {

        $t = $this->readToken();

        if (is_array($type)) {
            if (!in_array($t->type, $type)) {
                $s = print_r($type, true);
                throw new \Exception("Expected token of types: {$s}, got {$t->type}. Expression: {$this->expression}, pos: {$this->pos}");
            }
        } else {
            if ($t->type !== $type) {
                throw new \Exception("Expected token of type: {$type}, got {$t->type}. Expression: {$this->expression}, pos: {$this->pos}");
            }
        }

        return $t->type;
    }

    public function evaluate() {
        return $this->expression();
    }

    protected function expression() {

        $token = $this->readToken();
        $eval = null;


        if ($token->type === Token::OPEN_PARENTHESIS) {
            $eval = $this->expression();
            $this->expect(Token::CLOSE_PARENTHESIS);
        }

        if ($token->type === Token::VARIABLE_NAME) {

            $operator = $this->readToken();
            $quoteType = $this->expect([Token::SINGLE_QUOTE, Token::DOUBLE_QUOTE]);
            $value = $this->value($quoteType == Token::SINGLE_QUOTE ? "'" : '"');
            // same quote type as open quote
            $this->expect($quoteType);

            if ($operator->type === Token::OPERATOR_EQUAL) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] == $value : false;
            } elseif ($operator->type === Token::OPERATOR_NOT_EQUAL) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] != $value : false;
            } elseif ($operator->type === Token::OPERATOR_GT) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] > $value : false;
            } elseif ($operator->type === Token::OPERATOR_GET) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] >= $value : false;
            } elseif ($operator->type === Token::OPERATOR_LT) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] < $value : false;
            } elseif ($operator->type === Token::OPERATOR_LET) {
                $eval = isset($this->variableValues[$token->value]) ? $this->variableValues[$token->value] <= $value : false;
            } elseif ($operator->type === Token::OPERATOR_RLIKE) {
                $eval = isset($this->variableValues[$token->value]) ? (preg_match("#{$value}#", $this->variableValues[$token->value]) ? true : false) : false;
            } else {
                throw new \Exception("Expected an operator, got {$operator->type}");
            }
        }

        if ($eval === null) {
            throw new \Exception("Unexpected token: {$token->type}");
        }

        $t = $this->readToken(true);

        if ($t->type === Token::OPERATOR_AND) {
            return $this->AndX($eval);
        }

        if ($t->type === Token::OPERATOR_OR) {
            return $this->orX($eval);
        }

        if ($t->type === Token::EOF) {
            return $eval;
        }

        if ($t->type === Token::CLOSE_PARENTHESIS) {
            return $eval;
        }

        throw new \Exception("LAST Unexpected token: {$t->type}, pos: {$this->pos}");
    }

    protected function AndX($leftSide) {
        $andToken = $this->readToken();
        $rightSide = $this->expression();
        return $leftSide && $rightSide;
    }

    protected function OrX($leftSide) {
        $orToken = $this->readToken();
        $rightSide = $this->expression();
        return $leftSide || $rightSide;
    }

    protected function value($separator) {
        return $this->readVariableValue($separator);
    }

}
