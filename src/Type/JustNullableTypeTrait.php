<?php declare(strict_types = 1);

namespace PHPStan\Type;

use PHPStan\TrinaryLogic;

trait JustNullableTypeTrait
{

	/**
	 * @return string|null
	 */
	public function getClass()
	{
		return null;
	}

	/**
	 * @return string[]
	 */
	public function getReferencedClasses(): array
	{
		return [];
	}

	public function combineWith(Type $otherType): Type
	{
		if ($otherType instanceof $this) {
			return new self();
		}

		/** @var \PHPStan\Type\Type $thisType */
		$thisType = $this;
		return TypeCombinator::union($thisType, $otherType);
	}

	public function accepts(Type $type): bool
	{
		if ($type instanceof $this) {
			return true;
		}

		if ($type instanceof CompoundType) {
			return CompoundTypeHelper::accepts($type, $this);
		}

		return false;
	}

	public function isSupersetOf(Type $type): TrinaryLogic
	{
		if ($type instanceof self) {
			return TrinaryLogic::createYes();
		}

		if ($type instanceof CompoundType) {
			return $type->isSubsetOf($this);
		}

		return TrinaryLogic::createNo();
	}

	public function isDocumentableNatively(): bool
	{
		return true;
	}

	public function isIterable(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getIterableKeyType(): Type
	{
		return new ErrorType();
	}

	public function getIterableValueType(): Type
	{
		return new ErrorType();
	}

}
