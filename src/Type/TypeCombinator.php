<?php declare(strict_types = 1);

namespace PHPStan\Type;

class TypeCombinator
{

	/** @var bool|null */
	private static $unionTypesEnabled;

	public static function setUnionTypesEnabled(bool $enabled)
	{
		if (self::$unionTypesEnabled !== null) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		self::$unionTypesEnabled = $enabled;
	}

	public static function isUnionTypesEnabled(): bool
	{
		if (self::$unionTypesEnabled === null) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return self::$unionTypesEnabled;
	}

	public static function addNull(Type $type): Type
	{
		return self::union($type, new NullType());
	}

	public static function remove(Type $fromType, Type $typeToRemove): Type
	{
		if ($typeToRemove instanceof UnionType) {
			foreach ($typeToRemove->getTypes() as $unionTypeToRemove) {
				$fromType = self::remove($fromType, $unionTypeToRemove);
			}

			return $fromType;
		}
		$typeToRemoveDescription = $typeToRemove->describe();
		if ($fromType->describe() === $typeToRemoveDescription) {
			return new ErrorType();
		}
		if (
			$fromType instanceof BooleanType
			&& $typeToRemove instanceof TrueOrFalseBooleanType
		) {
			return new ErrorType();
		}
		if (
			$fromType instanceof MixedType
			|| !$fromType instanceof UnionType
		) {
			return $fromType;
		}

		$types = [];
		foreach ($fromType->getTypes() as $innerType) {
			if (!$typeToRemove->isSupersetOf($innerType)->yes()) {
				$types[] = $innerType;
			}
		}

		return self::union(...$types);
	}

	public static function removeNull(Type $type): Type
	{
		return self::remove($type, new NullType());
	}

	public static function containsNull(Type $type): bool
	{
		if ($type instanceof UnionType) {
			foreach ($type->getTypes() as $innerType) {
				if ($innerType instanceof NullType) {
					return true;
				}
			}

			return false;
		}

		return $type instanceof NullType;
	}

	public static function union(Type ...$types): Type
	{
		// transform A | (B | C) to A | B | C
		for ($i = 0; $i < count($types); $i++) {
			if ($types[$i] instanceof UnionType) {
				array_splice($types, $i, 1, $types[$i]->getTypes());
			}
		}

		// simplify true | false to bool
		for ($i = 0; $i < count($types); $i++) {
			for ($j = $i + 1; $j < count($types); $j++) {
				if ($types[$i] instanceof TrueBooleanType && $types[$j] instanceof FalseBooleanType) {
					$types[$i] = new TrueOrFalseBooleanType();
					array_splice($types, $j, 1);
					continue 2;
				} elseif ($types[$i] instanceof FalseBooleanType && $types[$j] instanceof TrueBooleanType) {
					$types[$i] = new TrueOrFalseBooleanType();
					array_splice($types, $j, 1);
					continue 2;
				}
			}
		}

		// transform A | A to A
		// transform A | never to A
		// transform true | bool to bool
		for ($i = 0; $i < count($types); $i++) {
			for ($j = $i + 1; $j < count($types); $j++) {
				if ($types[$j]->isSupersetOf($types[$i])->yes()) {
					array_splice($types, $i--, 1);
					continue 2;

				} elseif ($types[$i]->isSupersetOf($types[$j])->yes()) {
					array_splice($types, $j--, 1);
					continue 1;
				}
			}
		}

		if (count($types) === 0) {
			return new NeverType();

		} elseif (count($types) === 1) {
			return $types[0];
		}

		return new CommonUnionType($types);
	}

	public static function intersect(Type ...$types): Type
	{
		// transform A & (B | C) to (A & B) | (A & C)
		foreach ($types as $i => $type) {
			if ($type instanceof UnionType) {
				$topLevelUnionSubTypes = [];
				foreach ($type->getTypes() as $innerUnionSubType) {
					$topLevelUnionSubTypes[] = self::intersect(
						$innerUnionSubType,
						...array_slice($types, 0, $i),
						...array_slice($types, $i + 1)
					);
				}

				return self::union(...$topLevelUnionSubTypes);
			}
		}

		// transform A & (B & C) to A & B & C
		foreach ($types as $i => &$type) {
			if ($type instanceof IntersectionType) {
				array_splice($types, $i, 1, $type->getTypes());
			}
		}

		// transform IntegerType & ConstantIntegerType to ConstantIntegerType
		// transform Child & Parent to Child
		// transform Object & ~null to Object
		// transform A & A to A
		// transform int[] & string to never
		// transform callable & int to never
		// transform A & ~A to never
		// transform int & string to never
		for ($i = 0; $i < count($types); $i++) {
			for ($j = $i + 1; $j < count($types); $j++) {
				$isSupersetA = $types[$j]->isSupersetOf($types[$i]);
				if ($isSupersetA->no()) {
					return new NeverType();

				} elseif ($isSupersetA->yes()) {
					array_splice($types, $j--, 1);
					continue;
				}

				$isSupersetB = $types[$i]->isSupersetOf($types[$j]);
				if ($isSupersetB->maybe()) {
					continue;

				} elseif ($isSupersetB->yes()) {
					array_splice($types, $i--, 1);
					continue 2;
				}
			}
		}

		if (count($types) === 1) {
			return $types[0];

		} else {
			return new IntersectionType($types);
		}
	}

	public static function shouldSkipUnionTypeAccepts(UnionType $unionType): bool
	{
		$typesLimit = self::containsNull($unionType) ? 2 : 1;
		return !self::isUnionTypesEnabled() && count($unionType->getTypes()) > $typesLimit;
	}

}
