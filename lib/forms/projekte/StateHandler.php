<?php

namespace forms\projekte;

use forms\projekte\exceptions\IllegalStateException;
use forms\projekte\exceptions\IllegalTransitionException;
use framework\auth\AuthHandler;
use InvalidArgumentException;

class StateHandler{
    /**
     * @var string
     */
    private $actualState;
    /**
     * @var array
     */
    private $transitions;
    
    /**
     * @var array
     */
    private $validations;
    /**
     * @var array
     */
    private $postTransitionHooks;
    /**
     * @var array
     */
    private $states;
    
    private $parentTableName;
    
    /**
     * StateHandler constructor.
     *
     * @param        $parentTableName
     * @param array  $allStates
     * @param array  $transitions
     * @param array  $validations
     * @param array  $postTransitionHooks
     * @param string $start if empty or null, draft will be picked if available, otherwise first entry in states
     */
    public function __construct($parentTableName, $allStates, $transitions, $validations = [], $postTransitionHooks = [], $start = null){
        $this->parentTableName = $parentTableName;
        if (!is_array($allStates) || !is_array($transitions)){
            throw new InvalidArgumentException("Keine Arrays in States / Transitions übergeben!");
        }
        
        if ($start === null || empty($start)){
            if (isset($allStates["draft"])) {
                $start = "draft";
            } else {
                $start = array_keys($allStates)[0];
            }
        }
        
        $this->actualState = $start;
        $this->states = $allStates;
        
        foreach ($this->states as $state => $desc){
            if (!isset($validations[$state])){
                $validations[$state] = static function($newState){
                    return true;
                };
            }else if (!is_callable($validations[$state])){
                throw new InvalidArgumentException("Validator zu $state ist keine Funktion!");
            }
            if (!isset($postTransitionHooks[$state])){
                $postTransitionHooks[$state] = static function($newState){
                    return true;
                };
            }else if (!is_callable($postTransitionHooks[$state])){
                throw new InvalidArgumentException("Validator zu $state ist keine Funktion!");
            }
            if (!isset($transitions[$state])){
                throw new InvalidArgumentException("Cannot find state '$state' in \$transition array as key");
            }
        }
        $this->transitions = $transitions;
        $this->validations = $validations;
        $this->postTransitionHooks = $postTransitionHooks;
        $this->parentTableName = $parentTableName;
    }
    
    /**
     * @return array
     */
    public function getStates(): array{
        return $this->states;
    }
    
    /**
     * @param $newState
     *
     * @return bool
     *
     * @throws IllegalStateException
     * @throws IllegalTransitionException
     */
    public function transitionTo($newState){
        if (!$this->isExitingState($newState)) {
            throw new IllegalStateException("$newState nicht bekannt!");
        }
        if (!$this->isTransitionableTo($newState)){
            throw new IllegalTransitionException("$this->actualState nicht in $newState überführbar - Daten fehlen!");
        }
        if (!$this->isAllowedToTransitionTo($newState)){
            throw new IllegalTransitionException("$this->actualState nicht in $newState überführbar - nicht die passenden Rechte!");
        }
        $oldState = $this->actualState;
        $this->actualState = $newState;
        if (isset($this->postTransitionHooks[$oldState])){
            return $this->postTransitionHooks[$oldState]($newState);
        }
        return true;
    }
    
    /**
     * @param $state
     *
     * @return bool
     */
    private function isExitingState($state): bool{
        return isset($this->states[$state]);
    }
    
    /**
     * @param $newState string
     *
     * @return bool
     */
    public function isTransitionableTo($newState): bool{
        if ($this->isExitingState($newState)){
            //var_dump(["$newState" => $this->transitions[$this->actualState][$newState]]);

            if ($this->transitions[$newState]) {
                return $this->validations[$this->actualState]($newState);
            }

        }
        die("$newState nicht bekannt!");
    }
    
    public function isAllowedToTransitionTo($newState): bool{
        if (isset($this->transitions[$this->actualState][$newState])){
            return $this->checkPermissionArray($this->transitions[$this->actualState][$newState]);
        }

        return false;

    }
    
    private function checkPermissionArray($permArray): bool
    {
        //TODO: use same function as in PermissionHandler
        //var_dump($permArray);
        if ($permArray === true){
            return true;
        }
        $ret = AuthHandler::getInstance()->isAdmin();
        if (isset($permArray["groups"])) {
            $ret = $ret || AuthHandler::getInstance()->hasGroup($permArray["groups"]);
        }
        if (isset($permArray["gremien"])) {
            $ret = $ret || AuthHandler::getInstance()->hasGremium($permArray["gremien"]);
        }
        if (isset($permArray["persons"])){
            $ret = $ret || in_array(AuthHandler::getInstance()->getUsername(), $permArray["persons"], true);
            $ret = $ret || in_array(AuthHandler::getInstance()->getUserFullName(), $permArray["persons"], true);
        }
        //var_dump($ret);
        return $ret;
    }
    
    public function getAllAllowedTransitionableStates(): array
    {
        $ret = [];
        foreach ($this->states as $stateName => $desc){
            if ($this->isAllowedToTransitionTo($stateName)){
                $ret[] = $stateName;
            }
        }
        return $ret;
    }
    
    /**
     * @return string
     */
    public function getActualState(): string{
        return $this->actualState;
    }
    
    /**
     * @param $onlyValidChanges bool
     *
     * @return array
     */
    public function getNextStates($onlyValidChanges = false): array{
        return $this->getNextStatesFrom($this->actualState, $onlyValidChanges);
    }
    
    /**
     * @param $state            string
     * @param $onlyValidChanges bool
     *
     * @return array
     */
    public function getNextStatesFrom($state, $onlyValidChanges = false): array{
        $list = $this->transitions[$state];
        $ret = [];
        if ($onlyValidChanges === true){
            foreach ($list as $stateName => $condition){
                if ($this->isTransitionableTo($stateName) && $this->isAllowedToTransitionTo($stateName)){
                    $ret[] = $stateName;
                }
            }
        }else{
            $ret = array_keys($list);
        }
        return $ret;
    }
    
    public function getFullStateName(): string{
        return $this->getFullStateNameFrom($this->actualState);
    }
    
    /**
     * @param $state string
     *
     * @return string
     */
    public function getFullStateNameFrom(string $state): string
    {
        if (!$this->isExitingState($state)) {
            return false;
        }
        return $this->states[$state][0] ?? $state;
    }
    
    public function getAltStateName(): string
    {
        return $this->getAltStateNameFrom($this->actualState);
    }
    
    /**
     * @param $state string
     *
     * @return string
     */
    public function getAltStateNameFrom($state): string
    {
        return $this->states[$state][1] ?? $this->states[$state][0];
    }
    
}
