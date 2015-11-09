<?php

namespace Gdbots\QueryParser;

/**
 * A simple scanner for search queries. The scanner also functions as tokenizer, returning
 * only tokens and matched strings, instead of feeding character per character. The scanner
 * utilizes PHP built-in regular expressions to match tokens by order of priority. The
 * scanner needs to be steered manually with the "next" function to make it scan the next
 * token.
 *
 * Whitespace tokens are treated as separator between two semantic tokens and are
 * automatically discarded. Following classic tokenizers, tokens are represented by their:
 * - token type, in form of an integer constant. Technically, PHP can work fine with string
 *   representations for token types, but in this scanner, integers are used and a function
 *   is provided to convert the integer token type to textual representation.
 * - token content, in the form of a string.
 *
 * For debugging and error reporting reasons, the scanner retains all input to be processed,
 * all input that is processed and the position of the scanner in the original input string.
 */
class QueryLexer
{
    const T_EOI                 = 0; // end of input
    const T_WSPC                = 1; // white-space

    const T_WORD                = 2; // word
    const T_PHRASE              = 3; // text between two quotes
    const T_URL                 = 4; // url
    const T_DATE                = 5; // date
    const T_NUMBER              = 6; // number

    const T_EXCLUDE             = 10; // "-"
    const T_INCLUDE             = 11; // "+"
    const T_HASHTAG             = 12; // "#"
    const T_MENTION             = 13; // "@"

    const T_FILTER              = 20; // ":", ":>", ":<", ":>=", or ":<="
    const T_RANGE               = 21; // ":"+".."
    const T_BOOST               = 22; // "^"

    const T_OR_OPERATOR         = 30; // "OR"
    const T_AND_OPERATOR        = 31; // "AND"
    const T_OPEN_PARENTHESIS    = 32; // "("
    const T_CLOSE_PARENTHESIS   = 33; // ")"
    const T_QUOTE               = 34; // double quote
    const T_ILLEGAL             = 35; // illegal character

    // Match basic emoticons
    const REGEX_EMOTICONS_BASIC = '/(?<=^|\s)(?:>:\-?\(|:\-?\)|:\'\(|:\-?\|:\-?\/|:\-?\(|:\-?\*|:\-?\||:o\)|:\-?o|=\-?\)|:\-?D|:\-?p|:\-?P|:\-?b|;\-?p|;\-?P|;\-?b|;\-?\))/';

    // Match UTF-8 emoticons
    const REGEX_EMOTICONS_UTF8 = '/([\x{2712}\x{2714}\x{2716}\x{271d}\x{2721}\x{2728}\x{2733}\x{2734}\x{2744}\x{2747}\x{274c}\x{274e}\x{2753}-\x{2755}\x{2757}\x{2763}\x{2764}\x{2795}-\x{2797}\x{27a1}\x{27b0}\x{27bf}\x{2934}\x{2935}\x{2b05}-\x{2b07}\x{2b1b}\x{2b1c}\x{2b50}\x{2b55}\x{3030}\x{303d}\x{1f004}\x{1f0cf}\x{1f170}\x{1f171}\x{1f17e}\x{1f17f}\x{1f18e}\x{1f191}-\x{1f19a}\x{1f201}\x{1f202}\x{1f21a}\x{1f22f}\x{1f232}-\x{1f23a}\x{1f250}\x{1f251}\x{1f300}-\x{1f321}\x{1f324}-\x{1f393}\x{1f396}\x{1f397}\x{1f399}-\x{1f39b}\x{1f39e}-\x{1f3f0}\x{1f3f3}-\x{1f3f5}\x{1f3f7}-\x{1f4fd}\x{1f4ff}-\x{1f53d}\x{1f549}-\x{1f54e}\x{1f550}-\x{1f567}\x{1f56f}\x{1f570}\x{1f573}-\x{1f579}\x{1f587}\x{1f58a}-\x{1f58d}\x{1f590}\x{1f595}\x{1f596}\x{1f5a5}\x{1f5a8}\x{1f5b1}\x{1f5b2}\x{1f5bc}\x{1f5c2}-\x{1f5c4}\x{1f5d1}-\x{1f5d3}\x{1f5dc}-\x{1f5de}\x{1f5e1}\x{1f5e3}\x{1f5ef}\x{1f5f3}\x{1f5fa}-\x{1f64f}\x{1f680}-\x{1f6c5}\x{1f6cb}-\x{1f6d0}\x{1f6e0}-\x{1f6e5}\x{1f6e9}\x{1f6eb}\x{1f6ec}\x{1f6f0}\x{1f6f3}\x{1f910}-\x{1f918}\x{1f980}-\x{1f984}\x{1f9c0}\x{3297}\x{3299}\x{a9}\x{ae}\x{203c}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21a9}\x{21aa}\x{231a}\x{231b}\x{2328}\x{2388}\x{23cf}\x{23e9}-\x{23f3}\x{23f8}-\x{23fa}\x{24c2}\x{25aa}\x{25ab}\x{25b6}\x{25c0}\x{25fb}-\x{25fe}\x{2600}-\x{2604}\x{260e}\x{2611}\x{2614}\x{2615}\x{2618}\x{261d}\x{2620}\x{2622}\x{2623}\x{2626}\x{262a}\x{262e}\x{262f}\x{2638}-\x{263a}\x{2648}-\x{2653}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267b}\x{267f}\x{2692}-\x{2694}\x{2696}\x{2697}\x{2699}\x{269b}\x{269c}\x{26a0}\x{26a1}\x{26aa}\x{26ab}\x{26b0}\x{26b1}\x{26bd}\x{26be}\x{26c4}\x{26c5}\x{26c8}\x{26ce}\x{26cf}\x{26d1}\x{26d3}\x{26d4}\x{26e9}\x{26ea}\x{26f0}-\x{26f5}\x{26f7}-\x{26fa}\x{26fd}\x{2702}\x{2705}\x{2708}-\x{270d}\x{270f}]|\x{23}\x{20e3}|\x{2a}\x{20e3}|\x{30}\x{20e3}|\x{31}\x{20e3}|\x{32}\x{20e3}|\x{33}\x{20e3}|\x{34}\x{20e3}|\x{35}\x{20e3}|\x{36}\x{20e3}|\x{37}\x{20e3}|\x{38}\x{20e3}|\x{39}\x{20e3}|\x{1f1e6}[\x{1f1e8}-\x{1f1ec}\x{1f1ee}\x{1f1f1}\x{1f1f2}\x{1f1f4}\x{1f1f6}-\x{1f1fa}\x{1f1fc}\x{1f1fd}\x{1f1ff}]|\x{1f1e7}[\x{1f1e6}\x{1f1e7}\x{1f1e9}-\x{1f1ef}\x{1f1f1}-\x{1f1f4}\x{1f1f6}-\x{1f1f9}\x{1f1fb}\x{1f1fc}\x{1f1fe}\x{1f1ff}]|\x{1f1e8}[\x{1f1e6}\x{1f1e8}\x{1f1e9}\x{1f1eb}-\x{1f1ee}\x{1f1f0}-\x{1f1f5}\x{1f1f7}\x{1f1fa}-\x{1f1ff}]|\x{1f1e9}[\x{1f1ea}\x{1f1ec}\x{1f1ef}\x{1f1f0}\x{1f1f2}\x{1f1f4}\x{1f1ff}]|\x{1f1ea}[\x{1f1e6}\x{1f1e8}\x{1f1ea}\x{1f1ec}\x{1f1ed}\x{1f1f7}-\x{1f1fa}]|\x{1f1eb}[\x{1f1ee}-\x{1f1f0}\x{1f1f2}\x{1f1f4}\x{1f1f7}]|\x{1f1ec}[\x{1f1e6}\x{1f1e7}\x{1f1e9}-\x{1f1ee}\x{1f1f1}-\x{1f1f3}\x{1f1f5}-\x{1f1fa}\x{1f1fc}\x{1f1fe}]|\x{1f1ed}[\x{1f1f0}\x{1f1f2}\x{1f1f3}\x{1f1f7}\x{1f1f9}\x{1f1fa}]|\x{1f1ee}[\x{1f1e8}-\x{1f1ea}\x{1f1f1}-\x{1f1f4}\x{1f1f6}-\x{1f1f9}]|\x{1f1ef}[\x{1f1ea}\x{1f1f2}\x{1f1f4}\x{1f1f5}]|\x{1f1f0}[\x{1f1ea}\x{1f1ec}-\x{1f1ee}\x{1f1f2}\x{1f1f3}\x{1f1f5}\x{1f1f7}\x{1f1fc}\x{1f1fe}\x{1f1ff}]|\x{1f1f1}[\x{1f1e6}-\x{1f1e8}\x{1f1ee}\x{1f1f0}\x{1f1f7}-\x{1f1fb}\x{1f1fe}]|\x{1f1f2}[\x{1f1e6}\x{1f1e8}-\x{1f1ed}\x{1f1f0}-\x{1f1ff}]|\x{1f1f3}[\x{1f1e6}\x{1f1e8}\x{1f1ea}-\x{1f1ec}\x{1f1ee}\x{1f1f1}\x{1f1f4}\x{1f1f5}\x{1f1f7}\x{1f1fa}\x{1f1ff}]|\x{1f1f4}\x{1f1f2}|\x{1f1f5}[\x{1f1e6}\x{1f1ea}-\x{1f1ed}\x{1f1f0}-\x{1f1f3}\x{1f1f7}-\x{1f1f9}\x{1f1fc}\x{1f1fe}]|\x{1f1f6}\x{1f1e6}|\x{1f1f7}[\x{1f1ea}\x{1f1f4}\x{1f1f8}\x{1f1fa}\x{1f1fc}]|\x{1f1f8}[\x{1f1e6}-\x{1f1ea}\x{1f1ec}-\x{1f1f4}\x{1f1f7}-\x{1f1f9}\x{1f1fb}\x{1f1fd}-\x{1f1ff}]|\x{1f1f9}[\x{1f1e6}\x{1f1e8}\x{1f1e9}\x{1f1eb}-\x{1f1ed}\x{1f1ef}-\x{1f1f4}\x{1f1f7}\x{1f1f9}\x{1f1fb}\x{1f1fc}\x{1f1ff}]|\x{1f1fa}[\x{1f1e6}\x{1f1ec}\x{1f1f2}\x{1f1f8}\x{1f1fe}\x{1f1ff}]|\x{1f1fb}[\x{1f1e6}\x{1f1e8}\x{1f1ea}\x{1f1ec}\x{1f1ee}\x{1f1f3}\x{1f1fa}]|\x{1f1fc}[\x{1f1eb}\x{1f1f8}]|\x{1f1fd}\x{1f1f0}|\x{1f1fe}[\x{1f1ea}\x{1f1f9}]|\x{1f1ff}[\x{1f1e6}\x{1f1f2}\x{1f1fc}])/u';

    // Match tokens
    const REGEX_TOKENS = '/(\:[\>|\<]?[\=]?|\-|\+|\#|\@|\^|\')/';

    // Match filter
    const REGEX_FILTER_KEY = '/^([_\p{L}][\d_.\p{L}]*)/';
    const REGEX_FILTER_VALUE = '/^([\"\'\d_.\-\p{L}]*)/';
    const REGEX_FILTER_OPERATOR = '/^(\:[\>|\<]?[\=]?)(.*)/';

    // Match date
    const REGEX_DATE = '/^(\d{4}[-\.\/]\d{2}[-\.\/]\d{2}+)(.*)/';
    const REGEX_DATE_FILTER = '/^(\d{4}[-\.\/]\d{2}[-\.\/]\d{2})(\.\.)(\d{4}[-\.\/]\d{2}[-\.\/]\d{2}+)(.*)/';

    // Match numbers
    const REGEX_NUMBER = '/^([-+]?\d*\.?\d+)(.*)/';
    const REGEX_NUMBER_FILTER = '/^([-+]?\d*\.?\d)(\.\.)([-+]?\d*\.?\d+)(.*)/';

    /**
     * The input string which has already been processed and data back into tokens.
     *
     * @var string
     */
    private $processed;

    /**
     * The input string to be processed. This is shortened as it is processed.
     *
     * @var string
     */
    private $input;

    /**
     * The position of the scanner relative to the original input string.
     *
     * @var int
     */
    private $position;

    /**
     * The last text / token that processed the scanner.
     *
     * @var string
     */
    private $token;

    /**
     * The type of token which has processed the scanner , denoted by the constant.
     *
     * @var int
     */
    private $tokenType;

    /**
     * Ignore query string operators. Will remove all parentheses and AND operators.
     *
     * @var bool
     */
    private $ignoreOperators = true;

    /**
     * The textual representation of the token types.
     *
     * @var array
     */
    public static $typeStrings = array (
        self::T_EOI               => 'EOI',
        self::T_WSPC              => 'WHITESPACE',

        self::T_WORD              => 'WORD',
        self::T_PHRASE            => 'PHRASE',
        self::T_URL               => 'URL',
        self::T_DATE              => 'DATE',
        self::T_NUMBER            => 'NUMBER',

        self::T_EXCLUDE           => 'EXCLUDE',
        self::T_INCLUDE           => 'INCLUDE',
        self::T_HASHTAG           => 'HASHTAG',
        self::T_MENTION           => 'MENTION',

        self::T_FILTER            => 'FILTER',
        self::T_RANGE             => 'RANGE',
        self::T_BOOST             => 'BOOST',

        self::T_OR_OPERATOR       => 'OR_OPERATOR',
        self::T_AND_OPERATOR      => 'AND_OPERATOR',
        self::T_OPEN_PARENTHESIS  => 'OPEN_PARENTHESIS',
        self::T_CLOSE_PARENTHESIS => 'CLOSE_PARENTHESIS',
        self::T_QUOTE             => 'QUOTE',
        self::T_ILLEGAL           => 'ILLEGAL'
    );

    /**
     * The regular expressions per token type their token type matches the input.
     * This expression must contain two sub-expressions: the first for the characters
     * that match the token scanned itself, the second for the remaining characters
     * in the string (normally "(*)." To record all the remaining characters.
     *
     * By not only to describe the characters -Well-match we have more control over
     * which characters do not have to match. For example, for keywords like "OR"
     * we can impose here that after "OR" a space or any non-word character should be.
     *
     * The order of the regular expressions are determinable firing order in which
     * the tokens will be matched. This is important in adjusting or adding expressions.
     *
     * For example, keywords will always have to come in front of the word token, or
     * the keyword will be considered as a word.
     *
     * Inspection of an illegal nature should always be the last, if no other expression matches.
     * The whitespace is best expressed in the first place.
     *
     * @var array
     */
    private $regEx = [

        // WSPC matches in (frequent) spaces, tabs and newlines.
        self::T_WSPC => '/^([ \t\n]+)(.*)/',

        // PHRASE matches every possible input between double quote.
        self::T_PHRASE => '/^(\"[^"]*\")(.*)/',

        // URL matches all url patterns
        self::T_URL => '/^([\w-]+:\/\/[^\s\/$.?#].[^\s]*)(.*)/',

        // DATE (fllow by space, boost, or range filter)
        self::T_DATE => '/^(\d{4}[-\.\/]\d{2}[-\.\/]\d{2}+)([\s|\^|\.\.|\)]?.*)/',

        // NUMBER (fllow by space, boost, or range filter)
        self::T_NUMBER => '/^([-+]?\d*\.?\d+)([\s|\^|\.\.|\)]?.*)/',

        // OR matches by keyword "OR" (case sensitive)
        // when no text follows after "OR".
        self::T_OR_OPERATOR => '/^(OR)(\b.*)/',

        // AND matches by keyword "AND" (case sensitive)
        // when no text follows after "AND".
        self::T_AND_OPERATOR => '/^(AND)(\b.*)/',

        // parentheses
        self::T_OPEN_PARENTHESIS => '/^(\()(.*)/',
        self::T_CLOSE_PARENTHESIS => '/^(\))(.*)/',

        // hyphen, colon, quote
        self::T_EXCLUDE => '/^(\-)(.*)/',
        self::T_INCLUDE => '/^(\+)(.*)/',
        self::T_HASHTAG => '/^(\#)(.*)/',
        self::T_MENTION => '/^(\@)(.*)/',
        self::T_FILTER  => '/^(\:[\>|\<]?[\=]?)(.*)/',
        self::T_RANGE   => '/^(\.\.)(.*)/',
        self::T_BOOST   => '/^(\^)(.*)/',
        self::T_QUOTE   => '/^(\")([^\"]*)$/',

        // WORD matches letters, numbers, underscores, hyphens and
        // points (think eg. To hello_world.101) Can not match up
        // truncation characters and accents, which should be
        // encapsulated in quotes.
        self::T_WORD => '/^([\S][^\s\:\^]*)(.*)/',

        // this should match with each character that is left over.
        self::T_ILLEGAL => '/^(.)(.*)/'
    ];

    /**
     * Displays the part of the input string that's already been processed.
     *
     * @return string
     */
    public function getProcessedData()
    {
        return $this->processed;
    }

    /**
     * Indicates the part of the input string remaining to be processed.
     *
     * @return string
     */
    public function getRemainingData()
    {
        return $this->input;
    }

    /**
     * Returns the position of the scanner in the original input string.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Sets whether or not to ignore query operators.
     *
     * @param bool
     *
     * @return self
     */
    public function setIgnoreOperator($bool)
    {
        $this->ignoreOperators = $bool;

        return $this;
    }

    /**
     * Reads the new input string and set the position to 0.
     *
     * @param string $input
     */
    public function readString($input)
    {
        // find all strings and rebuild input string with "OR"
        if (preg_match_all('/[^\s\(\)\#\^\"]+'.

                // parentheses
                '|'.'(\()'.
                '|'.'(\))'.

                // exclude
                '|'.'(\-[^\-\+\^\s\(\)]*)'.

                // include
                '|'.'(\+[^\-\+\^\s\(\)]*)'.

                // hashtag
                '|'.'(\#[\d_\p{L}]*[_\p{L}][\d_\p{L}]*)'.

                // mention
                '|'.'(\@[\d_\-\p{L}]*[_\-\p{L}][\d_\-\p{L}]*)'.

                // boost
                '|'.'(\^[-+]?\d*\.?\d+)'.

                // url
                '|'.'([\w-]+:\/\/[^\s\/$.?#].[^\s]*)'.

                // double quote
                '|'.'\"([^\"]*)\"'.

            '/', $input, $matches)
        ) {
            $matches = $matches[0];

            $matches = $this->cleanCharacters($matches, $input);
            $matches = $this->modifyCharacters($matches);

            $input = $this->generateInput($matches);
        }

        $this->input = $input;
        $this->processed = '';
        $this->position = 0;
    }

    /**
     * Phase 1: cleanup characters.
     *
     * @param array  $matches
     * @param string $input
     *
     * @return array
     */
    private function cleanCharacters(array $matches, $input)
    {
        for ($key = 0, $m = count($matches); $key < $m; $key++) {
            $value = $matches[$key];
            $value = trim($value);

            if ($this->ignoreOperators) {
                if ($value == 'AND') {
                    $value = 'OR';
                }

                $value = str_replace('(', '', $value);
                $value = str_replace(')', '', $value);
            }

            if (empty($value)) {
                unset($matches[$key]);

                continue;
            }

            // remove duplicate special characters
            $isPhase = false;
            if (preg_match('/\"([^\"]*)\"/', $value)) {
                $isPhase = true;
            }
            if (!$isPhase) {
                $value = preg_replace('/(?>\-)\K\-*/', '', $value);
                $value = preg_replace('/(?>\+)\K\+*/', '', $value);
                $value = preg_replace('/(?>\#)\K\#*/', '', $value);
                $value = preg_replace('/(?>\@)\K\@*/', '', $value);
                $value = preg_replace('/(?>\^)\K\^*/', '', $value);
                $value = preg_replace('/(?>\:)\K\:*/', '', $value);
                $value = preg_replace('/\:(?>\>)\K\>*/', '', $value);
                $value = preg_replace('/\:(?>\<)\K\<*/', '', $value);
            }

            // merge url string
            if (preg_match($this->regEx[self::T_URL], $value)) {
                $orgKey = $key;

                for ($key++; $key < $m; $key++) {
                    if (strpos($input, $value.$matches[$key]) !== false) {
                        $value .= $matches[$key];

                        unset($matches[$key]);
                    }

                    if (isset($matches[$key+1]) && strpos($input, $value.$matches[$key+1]) === false) {
                        break;
                    }
                }

                $matches[$orgKey] = $value;
            } else {
                $matches[$key] = $value;
            }
        }

        // reindex array
        $matches = array_values($matches);

        return $matches;
    }

    /**
     * Phase 2: modify special characters.
     *
     * @param array $values
     *
     * @return array
     */
    private function modifyCharacters(array $values)
    {
        $prevKey = -1;
        foreach ($values as $key => $value) {
            // delete value that is a special characters
            if (preg_match(self::REGEX_TOKENS, $value, $matches) && $matches[0] == $value) {
                unset($values[$key]);

                continue;
            }

            // use last boost value when boost-on-a-boost is used (ex: a^1^2 -> a^2)
            if ((
                isset($values[$key+1]) &&
                preg_match($this->regEx[self::T_BOOST], $value) &&
                preg_match($this->regEx[self::T_BOOST], $values[$key+1])
            ) ||

            // ignore bad filters (ex: #abc:1 -> #abc)
            (
                isset($values[$prevKey]) &&
                preg_match(self::REGEX_FILTER_OPERATOR, $value, $matches) &&
                preg_match(self::REGEX_FILTER_VALUE, $matches[2]) &&
                !preg_match(self::REGEX_FILTER_KEY, $values[$prevKey])
            ) ||

            // boost a parentheses (ex: (a b)^2 -> (a b))
            (
                isset($values[$prevKey]) && $values[$prevKey] == ')' &&
                preg_match($this->regEx[self::T_BOOST], $value)
            )) {
                unset($values[$key]);

                continue;
            }

            // use last filter value when filter-on-a-filter is used (ex: a:1:2 -> a:2)
            if (strpos($value, '"') === false && strpos($value, ':') !== false) {
                $tmp = explode(':', $value, 2);

                if (!preg_match($this->regEx[self::T_URL], $tmp[1])) {
                    $tmp = explode(':', $value);
                    $value = $tmp[0].':'.end($tmp);
                }
            }

            // add quotes to emoticons
            foreach ([self::REGEX_EMOTICONS_BASIC, self::REGEX_EMOTICONS_UTF8] as $regEx) {
                if (preg_match($regEx, $value, $matches) && $matches[0] == $value) {
                    $value = str_replace($matches[0], sprintf('"%s"', $matches[0]), $value);
                }
            }

            $values[$key] = $value;

            $prevKey = $key;
        }

        // reindex array
        $values = array_values($values);

        return $values;
    }

    /**
     * Phase 3: handle parentheses and add OR/AND expression.
     *
     * @param array $values
     *
     * @return string
     */
    private function generateInput(array $values)
    {
        $input = '';

        $openParenthesis = 0;

        foreach ($values as $key => $value) {
            if (empty($value)) {
                continue;
            }

            $input .= $value;

            if (preg_match_all('/(\()/', $value, $matches)) {
                $openParenthesis += count($matches[0]);
            }
            if (preg_match_all('/(\))/', $value, $matches)) {
                if (preg_match($this->regEx[self::T_PHRASE], $value, $phaseMatches)) {
                    if (preg_match_all('/(\))/', str_replace($phaseMatches[1], '', $value), $closeParenthesisMatches)) {
                        $openParenthesis -= count($closeParenthesisMatches[0]);
                    }
                } else {
                    $openParenthesis -= count($matches[0]);
                }
            }

            if (isset($values[$key+1]) &&
                (
                    (
                        !in_array(substr($values[$key+1], 0, 1), [':', '^', ')']) &&
                        !preg_match(self::REGEX_FILTER_OPERATOR, $values[$key+1])
                    ) ||
                    preg_match(self::REGEX_EMOTICONS_BASIC, $values[$key+1]) ||
                    (
                        preg_match(self::REGEX_TOKENS, $values[$key+1], $matches) &&
                        $matches[0] == $values[$key+1]
                    )
                ) &&
                (
                    (
                        !in_array(substr($value, -1), [':', '^']) &&
                        !preg_match(self::REGEX_FILTER_OPERATOR, substr($value, -2))
                    ) ||
                    preg_match(self::REGEX_EMOTICONS_BASIC, $value)
                )
            ) {
                if (!in_array($values[$key+1], ['AND', 'OR']) &&
                    !in_array($value, ['AND', 'OR', '('])
                ) {
                    $input .= ' OR ';
                } else {
                    $input .= ' ';
                }
            }
        }

        // add missing close parentheses
        for (; $openParenthesis<0; $openParenthesis++) {
            $input = '('.$input;
        }
        for (; $openParenthesis>0; $openParenthesis--) {
            $input .= ')';
        }

        // remove duplicate characters and spaces
        $input = preg_replace('/\s+/', ' ', $input);
        $input = preg_replace('/(OR|AND)(\s)(OR|AND)/', '$1', $input);
        $input = preg_replace('/(\()(\s?)(OR|AND)(\s?)/', '$1', $input);
        $input = preg_replace('/(\(\))(\s?)(OR|AND)(\s?)/', '', $input);
        $input = preg_replace('/(\()(\s)/', '$1', $input);
        $input = preg_replace('/(\s)(\))/', '$1', $input);
        $input = preg_replace('/(\()/', '$1 ', $input);
        $input = preg_replace('/(\))/', ' $1', $input);

        return $input;
    }

    /**
     * Return the current token type.
     *
     * @return int
     */
    public function getTokenType()
    {
        return $this->tokenType;
    }

    /**
     * Returns a textual version of the token type:
     * - the token type (constant) if given
     * - the last processed token if no parameter is passed
     *
     * @param int $tokenType
     *
     * @return string
     */
    public function getTokenTypeText($tokenType = null)
    {
        if ($tokenType == null) {
            $tokenType = $this->tokenType;
        }

        return self::$typeStrings[$tokenType];
    }

    /**
     * Returns the current token.
     *
     * @return int
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Processes the all tokens and indicates the current type.
     *
     * @return int
     */
    public function next()
    {
        // test each token type
        foreach ($this->regEx as $tokenType => $reg) {
            if ($this->testToken($reg, $tokenType) && $this->getTokenType() != self::T_WSPC) {
                return $this->getTokenType();
            }
        }

        // if no token matches, we are probably at the end. The control is
        // still entered, the "preg_match" expression failure for illegal
        // characters.
        if ($this->input != '') {
            $this->tokenType = self::T_ILLEGAL;
            $this->token = $this->input;
            $this->input = '';
            return self::T_ILLEGAL;
        }

        $this->tokenType = self::T_EOI;
        $this->token = null;

        return self::T_EOI;
    }

    /**
     * Test an expression for a match and process as token.
     *
     * @param string $regEx
     * @param int    $tokenType
     *
     * @return bool
     */
    private function testToken($regEx, $tokenType)
    {
        static $isFilter = false;

        if ($this->input == '') {
            $isFilter = false;

            return  false;
        }

        if (!is_array($regEx)) {
            $regEx = [$regEx];
        }

        foreach ($regEx as $re) {
            if (!preg_match($re, $this->input, $matches)) {
                continue;
            }

            if ($tokenType == self::T_WORD) {
                $matches = $this->testWorkToken($matches);
            }

            // ignore range not in filter
            if ((!$isFilter && $tokenType == self::T_RANGE) ||

                // ignore range+range
                ($isFilter && $this->tokenType == self::T_RANGE && $tokenType == self::T_RANGE) ||

                // ignore range+ non numeric or date
                ($isFilter && $this->tokenType == self::T_RANGE && !in_array($tokenType, [self::T_DATE, self::T_NUMBER])) ||

                // ignore invalid filter values
                ($tokenType == self::T_DATE && !$this->validateDateFilter($this->input, $isFilter)) ||

                ($tokenType == self::T_NUMBER && !$this->validateNumberFilter($this->input, $isFilter)) ||

                // ignore range when filter operator is not equal
                ($isFilter && !$this->validateRangeFilter($this->token, $tokenType, $this->input))
            ) {
                return false;
            }

            $this->token = $matches[1];
            $this->processed .= $matches[1];
            $this->input = $matches[2];
            $this->tokenType = $tokenType;
            $this->position = $this->position + strlen($this->token);

            if ($tokenType == self::T_FILTER) {
                $isFilter = true;
            }
            if ($tokenType == self::T_WSPC) {
                $isFilter = false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array $values
     *
     * @return array
     */
    private function testWorkToken(array $values)
    {
        // word+filter with no value (ex: "a:")
        if (preg_match(self::REGEX_TOKENS, $values[2], $matches) && $matches[0] == $values[2]) {
            $values[1] = $values[0];
            $values[2] = '';
        }

        // ignore non-filters (ex: "http://")
        if (preg_match(self::REGEX_FILTER_OPERATOR, $values[2], $matches)) {
            if (preg_match(self::REGEX_FILTER_VALUE, $matches[2], $filterValueMatches) && !$filterValueMatches[0]) {
                $tmp = explode(' ', $matches[2], 2);
                $values[1] = $values[1].$matches[1].$tmp[0];
                $values[2] = isset($tmp[1]) ? $tmp[1] : '';
            }
        }

        return $values;
    }

    /**
     * @param string input
     * @param bool   $isFilter
     */
    private function validateDateFilter($input, $isFilter = false)
    {
        return (
            (
                preg_match(self::REGEX_DATE, $input, $matches) &&
                (!$matches[2] || in_array(substr($matches[2], 0, 1), [' ', '^']))
            ) ||
            (
                $isFilter &&
                preg_match(self::REGEX_DATE_FILTER, $input, $matches) &&
                (!$matches[4] || in_array(substr($matches[4], 0, 1), [' ', '^']))
            )
        );
    }

    /**
     * @param string $input
     * @param bool   $isFilter
     */
    private function validateNumberFilter($input, $isFilter = false)
    {
        return (
            (
                preg_match(self::REGEX_NUMBER, $input, $matches) &&
                (!$matches[2] || in_array(substr($matches[2], 0, 1), [' ', '^']))
            ) ||
            (
                $isFilter &&
                preg_match(self::REGEX_NUMBER_FILTER, $input, $matches) &&
                (!$matches[4] || in_array(substr($matches[4], 0, 1), [' ', '^']))
            )
        );
    }

    /**
     * @param string $token
     * @param int    $tokenType
     * @param string $input
     */
    private function validateRangeFilter($token, $tokenType, $input)
    {
        return !(
            !in_array($token, [':', ':=']) &&
            (
                ($tokenType == self::T_DATE && preg_match(self::REGEX_DATE_FILTER, $input)) ||
                ($tokenType == self::T_NUMBER && preg_match(self::REGEX_NUMBER_FILTER, $input))
            )
        );
    }
}
