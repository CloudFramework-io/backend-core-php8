<?php
/**
 * https://cloudframework.io
 * Script Template
 */
class Script extends Scripts2020
{
    /**
     * This function is executed as the main method of the class
     */
    function main()
    {
        // We take parameter 1 to stablish the method to call when you execute: composer script hello/parameter-1/parameter-2/..
        // If the parameter 1 is empty we assign default by default :)
        $method = (isset($this->params[1])) ? $this->params[1] : 'default';
        // we convert - by _ because a method name does not allow '-' symbol
        $method = str_replace('-', '_', $method);

        //Call internal ENDPOINT_{$method}
        if (!$this->useFunction('METHOD_' . $method)) {
            return ($this->setErrorFromCodelib('params-error', "/{$method} is not implemented"));
        }
    }

    /**
     * Show default tests
     */
    public function METHOD_default()
    {
        return $this->METHOD_security();


    }

    /**
     * Show default tests
     */
    public function METHOD_security()
    {
        $this->METHOD_security_crypt();
    }

    /**
     * Show default tests
     */
    public function METHOD_security_crypt()
    {
        $this->sendTerminal('Testing $this->core->security');

        //region ->crypt($input). SET $input, $input_encrypted, $input2, $input2_encrypted, $input3
        $this->sendTerminal(' # $this->core->security->crypt($input);');
        // Generate a random $input
        $input = uniqid('Test');// string input
        $input2 = ['whatever'=>uniqid('Test')];     // array input
        $input_with_72_chars = uniqid('Test')."0123456789012345678901234567890123456789012345678901234";// string input
        $input_more_than_72_chars = uniqid('Test')."01234567890123456789012345678901234567890123456789012345";// string input

        // Encrypt the output
        $input_encrypted = $this->core->security->crypt($input);
        $input2_encrypted = $this->core->security->crypt($input2);
        $input_with_72_chars_encrypted = $this->core->security->crypt($input_with_72_chars);
        $input_more_than_72_chars_encrypted = $this->core->security->crypt($input_more_than_72_chars);

        if(!$input_encrypted) return($this->addError(' $this->core->security->crypt(string $input) has returned an empty string'));
        if(!$input2_encrypted) return($this->addError(' $this->core->security->crypt(array $input) has returned an empty string'));
        if(!$input_with_72_chars_encrypted) return($this->addError(' $this->core->security->crypt(sting $input_with_72_chars_encrypted) has returned an empty string'));
        if($input_more_than_72_chars_encrypted) return($this->addError(' $this->core->security->crypt(sting $input_more_than_72_chars_encrypted) has returned a NOT empty string'));
        if($this->core->security->crypt(null) !== null) return($this->addError('$this->core->security->crypt(null) has returned a no null value'));
        if($this->core->security->crypt("") !== null) return($this->addError('$this->core->security->crypt("") has returned a no null value'));
        $this->sendTerminal('   - OK to encrypting a string [$input, $input_encrypted]');
        $this->sendTerminal('   - OK to encrypting an array [$input2, $input2_encrypted]');
        $this->sendTerminal('   - OK sending a null value and it returns null');
        $this->sendTerminal('   - OK sending a "" value and it returns null');
        //endregion

        //region ->checkCrypt($input,$input_encrypted)
        $this->sendTerminal(' # $this->core->security->checkCrypt($input,$input_encrypted);');

        if(!$this->core->security->checkCrypt($input,$input_encrypted)) return($this->addError('$this->core->security->checkCrypt($input,$input_encrypted) has return false'));
        $this->sendTerminal('   - OK checking $input with $input_encrypted');
        if(!$this->core->security->checkCrypt($input2,$input2_encrypted)) return($this->addError('$this->core->security->checkCrypt($input,$input_encrypted) has return false'));
        $this->sendTerminal('   - OK checking $input2 with $input2_encrypted');
        if(!$this->core->security->checkCrypt($input_with_72_chars,$input_with_72_chars_encrypted)) return($this->addError('$this->core->security->checkCrypt($input_with_72_chars,$input_with_72_chars_encrypted) has return false'));
        $this->sendTerminal('   - OK checking $input3 with $input3_encrypted');

        if($this->core->security->checkCrypt($input,"")) return($this->addError('$this->core->security->checkCrypt($input,"") has return true'));
        if($this->core->security->checkCrypt($input,"whatever")) return($this->addError('$this->core->security->checkCrypt($input,"whatever") has return true'));
        if($this->core->security->checkCrypt($input."s",$input_encrypted)) return($this->addError('$this->core->security->checkCrypt($input."s",$input_encrypted) has return true when first parameter is not correct'));
        if($this->core->security->checkCrypt($input_with_72_chars."s",$input_with_72_chars_encrypted)) return($this->addError('$this->core->security->checkCrypt($input_with_72_chars."s",$input_with_72_chars_encrypted) has return true when first parameter is not correct'));
        if($this->core->security->checkCrypt($input,$input_encrypted."s")) return($this->addError('$this->core->security->checkCrypt($input,$input_encrypted."s") has return true when first parameter is not correct'));
        if($this->core->security->checkCrypt("","")) return($this->addError('$this->core->security->checkCrypt("","") has return true when first parameter is not correct'));
        $this->sendTerminal('   - OK checking negative scenarios');


        //endregion
    }

}